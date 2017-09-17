<?php

namespace Simplario\Quedis;

use Simplario\Quedis\Exceptions\FlowException;
use Simplario\Quedis\Exceptions\QueueException;
use Simplario\Quedis\Interfaces\QueueInterface;

/**
 * Class Queue
 *
 * @package Simplario\Quedis
 *
 *
 *
 *   Message flows (like in the Beanstalk: http://beanstalkc.readthedocs.io/en/latest/tutorial.html )
 *   ------------------------------------------------------------------------------------------------
 *
 *   1)   put            pop
 *       -----> [READY] --------> *poof*
 *
 *
 *   2)   put            reserve               delete
 *       -----> [READY] ---------> [RESERVED] --------> *poof*
 *
 *
 *   3)   put with delay               release with delay
 *       ----------------> [DELAYED] <------------.
 *                             |                   |
 *                             | (time passes)     |
 *                             |                   |
 *        put                  v     reserve       |       delete
 *       -----------------> [READY] ---------> [RESERVED] --------> *poof*
 *                            ^  ^                |  |
 *                            |   \  release      |  |
 *                            |    ``-------------'   |
 *                            |                      |
 *                            | kick                 |
 *                            |                      |
 *                            |       bury           |
 *                         [BURIED] <---------------'
 *                            |
 *                            |  delete
 *                             ``--------> *poof*
 *
 */
class Queue implements QueueInterface
{

    const PRIORITY_HIGH = 'high';
    const PRIORITY_LOW = 'low';

    const NS_QUEUE = 'tube';
    const NS_QUEUE_STOP = 'tube2stop';
    const NS_MESSAGE = 'message';
    const NS_MESSAGE_TO_TUBE = 'message2tube';
    const NS_MESSAGE_TO_STATE = 'message2state';

    // State
    const STATE_READY = 'ready';
    const STATE_DELAYED = 'delayed';
    const STATE_RESERVED = 'reserved';
    const STATE_BURIED = 'buried';

    // Stats
    const STATS_QUEUES_LIST = 'tubes';
    const STATS_QUEUES = 'queues';
    const STATS_MESSAGE_TOTAL = 'total';
    const STATS_MESSAGE_READY = 'ready';
    const STATS_MESSAGE_RESERVED = 'reserved';
    const STATS_MESSAGE_DELAYED = 'delayed';
    const STATS_MESSAGE_BURIED = 'buried';
    const STATS_QUEUE_STOP = 'stop';

    /**
     * @var \Predis\Client
     */
    protected $redis;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * Queue constructor.
     *
     * @param        $redis
     * @param string $namespace
     */
    public function __construct($redis, $namespace = 'Quedis')
    {
        $this->redis = $redis;
        $this->namespace = $namespace;
    }

    /**
     * @return \Predis\Client
     */
    public function getRedis()
    {
        return $this->redis;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    // Message Flow =============================================================================
    // ==========================================================================================

    /**
     * @param string $tube
     * @param        $data
     * @param int    $delay
     * @param string $priority
     *
     * @return Message
     * @throws \Exception
     */
    public function put($tube, $data, $delay = 0, $priority = self::PRIORITY_LOW)
    {
        $message = $this->createMessage($data);
        $delay = $this->parseDelay($delay);

        $result = $this->getRedis()->transaction(function ($tx) use ($tube, $message, $delay, $priority) {
            /** @var $tx \Predis\Client */

            $state = $delay == 0 ? self::STATE_READY : self::STATE_DELAYED;

            $tx->hset($this->ns(self::NS_MESSAGE), $message->getToken(), $message->encode());
            $tx->hset($this->ns(self::NS_MESSAGE_TO_TUBE), $message->getToken(), $tube);
            $tx->hset($this->ns(self::NS_MESSAGE_TO_STATE), $message->getToken(), $state);

            if ($state === self::STATE_READY) {
                if (self::PRIORITY_HIGH === $priority) {
                    $tx->lpush($this->getKey($tube, self::STATE_READY), $message->getToken());
                } else {
                    $tx->rpush($this->getKey($tube, self::STATE_READY), $message->getToken());
                }
            } else {
                $tx->zadd($this->getKey($tube, self::STATE_DELAYED), time() + $delay, $message->getToken());
            }
        });

        if (in_array(false, $result, true)) {
            throw new QueueException('Can not put message to queue');
        }

        return $message;
    }

    /**
     * @param               $tube
     * @param int           $timeout
     * @param callable|null $callback
     *
     * @return mixed|null|Queue
     */
    public function pop($tube, $timeout = 0, callable $callback = null)
    {
        if (is_callable($callback)) {
            $callback = function (Message $message = null, Queue $queue) use ($callback) {
                $this->delete($message);
                $callback($message, $queue);
            };

            return $this->reserve($tube, $timeout, $callback);
        }

        $message = $this->reserve($tube, $timeout, $callback);

        if ($message !== null) {
            $this->delete($message);
        }

        return $message;
    }

    /**
     * @param               $tube
     * @param int           $timeout
     * @param callable|null $callback
     *
     * @return mixed|null|static
     */
    public function reserve($tube, $timeout = 0, callable $callback = null)
    {
        if ($this->isStop($tube)) {
            return null;
        }

        $this->migrate($tube);

        $redis = $this->getRedis();

        if ($timeout > 0) {
            // example return [ '0' => tube name , '1' => jobId ]
            $token = $redis->blpop([$this->getKey($tube, self::STATE_READY)], $timeout);
            $token = (is_array($token)) ? $token[1] : null;
        } else {
            $token = $redis->lpop($this->getKey($tube, self::STATE_READY));
        }

        if (!is_null($token)) {
            $encoded = $redis->hget($this->ns(self::NS_MESSAGE), $token);

            if ($encoded === null) {
                return null;
            }

            $message = Message::decode($encoded);
            $redis->zadd($this->getKey($tube, self::STATE_RESERVED), time(), $token);
            $redis->hset($this->ns(self::NS_MESSAGE_TO_STATE), $token, self::STATE_RESERVED);

            if (is_callable($callback)) {
                $callback($message, $this);
                return $this->reserve($tube, $timeout, $callback);
            }

            return $message;
        }

        return null;
    }

    /**
     * @param Message|string $mixed
     *
     * @return $this
     * @throws QueueException
     */
    public function bury($mixed)
    {
        $payload = $this->payload($mixed);
        $this->checkMessageFlow($payload->getState(), 'bury');

        $result = $this->getRedis()->transaction(function ($tx) use ($payload) {
            /** @var $tx \Predis\Client */
            $tx->zrem($this->getKey($payload->getQueue(), self::STATE_RESERVED), $payload->getToken());
            $tx->zadd($this->getKey($payload->getQueue(), self::STATE_BURIED), time(), $payload->getToken());
            $tx->hset($this->ns(self::NS_MESSAGE_TO_STATE), $payload->getToken(), self::STATE_BURIED);
        });

        if (in_array(false, $result, true)) {
            throw new QueueException("The message bury error.");
        }

        return $this;
    }

    /**
     * @param Message|string $mixed
     *
     * @return $this
     * @throws QueueException
     */
    public function delete($mixed)
    {
        $payload = $this->payload($mixed);
        $this->checkMessageFlow($payload->getState(), 'delete');

        $result = $this->getRedis()->transaction(function ($tx) use ($payload) {
            /** @var $tx \Predis\Client */
            $tx->zrem($this->getKey($payload->getQueue(), self::STATE_RESERVED), $payload->getToken());
            $tx->zrem($this->getKey($payload->getQueue(), self::STATE_BURIED), $payload->getToken());
            $tx->zrem($this->getKey($payload->getQueue(), self::STATE_DELAYED), $payload->getToken()); // ?
            $tx->hdel($this->ns(self::NS_MESSAGE), $payload->getToken());
            $tx->hdel($this->ns(self::NS_MESSAGE_TO_TUBE), $payload->getToken());
            $tx->hdel($this->ns(self::NS_MESSAGE_TO_STATE), $payload->getToken());
        });

        if (array_count_values($result) < 1) {
            throw new QueueException("The message deleting error");
        }

        return $this;
    }

    /**
     * @param Message|string $mixed
     *
     * @return $this
     * @throws QueueException
     */
    public function kick($mixed)
    {
        $payload = $this->payload($mixed);
        $this->checkMessageFlow($payload->getState(), 'kick');

        $result = $this->getRedis()->transaction(function ($tx) use ($payload) {
            /** @var $tx \Predis\Client */
            $tx->zrem($this->getKey($payload->getQueue(), self::STATE_BURIED), $payload->getToken());
            $tx->rpush($this->getKey($payload->getQueue(), self::STATE_READY), $payload->getToken());
            $tx->hset($this->ns(self::NS_MESSAGE_TO_STATE), $payload->getToken(), self::STATE_READY);
        });

        if (in_array(false, $result, true)) {
            throw new QueueException("The message kicked error");
        }

        return $this;
    }

    /**
     * @param     $mixed
     * @param int $delay
     *
     * @return $this
     * @throws FlowException
     * @throws QueueException
     */
    public function release($mixed, $delay = 0)
    {
        $payload = $this->payload($mixed);
        $this->checkMessageFlow($payload->getState(), 'release');
        $delay = $this->parseDelay($delay);

        $result = $this->getRedis()->transaction(function ($tx) use ($payload, $delay) {
            /** @var $tx \Predis\Client */
            $tx->zrem($this->getKey($payload->getQueue(), self::STATE_RESERVED), $payload->getToken());
            $tx->hset($this->ns(self::NS_MESSAGE_TO_STATE), $payload->getToken(), self::STATE_READY);

            if ($delay == 0) {
                $tx->rpush($this->getKey($payload->getQueue(), self::STATE_READY), $payload->getToken());
            } else {
                $tx->zadd($this->getKey($payload->getQueue(), self::STATE_DELAYED), time() + $delay,
                    $payload->getToken());
            }
        });

        if (in_array(false, $result, true)) {
            throw new QueueException("The message release error.");
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getQueueList()
    {
        $redis = $this->getRedis();
        $queueList = $redis->hgetall($this->ns(self::NS_MESSAGE_TO_TUBE));
        $queueList = array_values($queueList);
        $queueList = array_unique($queueList);

        return $queueList;
    }

    /**
     * @param null|string $queue
     *
     * @return $this
     */
    public function migrate($queue = null)
    {
        if (null === $queue) {
            $queueList = $this->getQueueList();
            foreach ($queueList as $tube) {
                $this->migrate($tube);
            }

            return $this;
        }

        $keyReady = $this->getKey($queue, self::STATE_READY);
        $keyDelayed = $this->getKey($queue, self::STATE_DELAYED);

        $this->getRedis()->transaction(['cas' => true, 'watch' => [$keyReady, $keyDelayed], 'retry' => 10],
            function ($tx) use ($queue, $keyReady, $keyDelayed) {

                /** @var $tx \Predis\Client */
                $time = time();

                // get expired jobs from "delayed queue"
                $messageTokenSet = $tx->zrangebyscore($keyDelayed, '-inf', $time);

                if (count($messageTokenSet) > 0) {
                    // remove jobs from "delayed queue"
                    $tx->multi();
                    $tx->zremrangebyscore($keyDelayed, '-inf', $time);
                    foreach ($messageTokenSet as $token) {
                        $tx->rpush($keyReady, $token);
                        $tx->hset($this->ns(self::NS_MESSAGE_TO_STATE), $token, self::STATE_READY);
                    }
                }
            });

        return $this;
    }

    /**
     * @param string $queue
     *
     * @return $this
     */
    public function start($queue)
    {
        $this->getRedis()->hdel($this->ns(self::NS_QUEUE_STOP), [$queue]);

        return $this;
    }

    /**
     * @param string $queue
     *
     * @return $this
     */
    public function stop($queue)
    {
        $this->getRedis()->hset($this->ns(self::NS_QUEUE_STOP), $queue, true);

        return $this;
    }

    /**
     * @param string $tube
     *
     * @return bool
     */
    public function isStop($tube)
    {
        return (bool)$this->getRedis()->hexists($this->ns(self::NS_QUEUE_STOP), $tube);
    }

    /**
     * @param $queue
     *
     * @return array
     */
    protected function queueStats($queue)
    {
        $result = [
            self::STATS_MESSAGE_READY    => $this->size($queue, self::STATE_READY),
            self::STATS_MESSAGE_RESERVED => $this->size($queue, self::STATE_RESERVED),
            self::STATS_MESSAGE_DELAYED  => $this->size($queue, self::STATE_DELAYED),
            self::STATS_MESSAGE_BURIED   => $this->size($queue, self::STATE_BURIED),
        ];

        $result[self::STATS_MESSAGE_TOTAL] = array_sum($result);
        $result[self::STATS_QUEUE_STOP] = $this->isStop($queue);

        return $result;
    }

    /**
     * @param null|string $queue
     *
     * @return array
     */
    public function stats($queue = null)
    {
        if ($queue !== null) {
            return $this->queueStats($queue);
        }

        $result = [
            self::STATS_QUEUES_LIST      => [],
            self::STATS_MESSAGE_TOTAL    => 0,
            self::STATS_MESSAGE_READY    => 0,
            self::STATS_MESSAGE_RESERVED => 0,
            self::STATS_MESSAGE_DELAYED  => 0,
            self::STATS_MESSAGE_BURIED   => 0,
        ];

        $queueList = $this->getQueueList();

        if (count($queueList) == 0) {
            return $result;
        }

        $result[self::STATS_QUEUES_LIST] = $queueList;

        foreach ($queueList as $queue) {
            $itemStats = $this->queueStats($queue);
            $result[self::STATS_MESSAGE_READY] += $itemStats[self::STATS_MESSAGE_READY];
            $result[self::STATS_MESSAGE_RESERVED] += $itemStats[self::STATS_MESSAGE_RESERVED];
            $result[self::STATS_MESSAGE_DELAYED] += $itemStats[self::STATS_MESSAGE_DELAYED];
            $result[self::STATS_MESSAGE_BURIED] += $itemStats[self::STATS_MESSAGE_BURIED];
            $result[self::STATS_MESSAGE_TOTAL] += $itemStats[self::STATS_MESSAGE_TOTAL];
            $result[self::STATS_QUEUES][$queue] = $itemStats;
        }

        return $result;
    }

    /**
     * @param        $queue
     * @param string $state
     *
     * @return int|string
     * @throws QueueException
     */
    public function size($queue, $state = self::STATE_READY)
    {
        if (!in_array($state, [self::STATE_READY, self::STATE_DELAYED, self::STATE_BURIED, self::STATE_RESERVED])) {
            throw new QueueException('Unsupported state for size calculation.');
        }

        $redis = $this->getRedis();

        return ($state === self::STATE_READY)
            ? $redis->llen($this->getKey($queue, self::STATE_READY))
            : $redis->zcount($this->getKey($queue, $state), '-inf', '+inf');
    }


    /**
     * @param null $queue
     *
     * @return $this
     */
    public function clean($queue = null)
    {
        $redis = $this->getRedis();

        $keys = $redis->keys($this->namespace . '*');

        if (count($keys) == 0) {
            return $this;
        }

        foreach ($keys as $index => $name) {
            $redis->del($name);
        }

        return $this;
    }

    /**
     * @param $mixed
     *
     * @return Message
     */
    protected function createMessage($mixed)
    {
        if ($mixed instanceof Message) {
            return $mixed;
        }

        return new Message($mixed);
    }

    /**
     * @param $type
     *
     * @return string
     */
    protected function ns($type)
    {
        return implode(':', [$this->namespace, $type]);
    }


    /**
     * @param $queue
     * @param $state
     *
     * @return string
     */
    protected function getKey($queue, $state)
    {
        return implode(':', [$this->ns(self::NS_QUEUE), $queue, $state]);
    }

    /**
     * @param int|\DateTime $mixed
     *
     * @return int
     */
    protected function parseDelay($mixed)
    {
        if ($mixed instanceof \DateTime) {
            $delay = $mixed->getTimestamp() - time();
        } else {
            $delay = (int)$mixed;
        }

        return $delay < 0 ? 0 : $delay;
    }

    /**
     * @param $currentState
     * @param $action
     *
     * @return $this
     * @throws FlowException
     */
    protected function checkMessageFlow($currentState, $action)
    {
        $mapping = [
            'bury'    => [self::STATE_RESERVED],
            'delete'  => [self::STATE_RESERVED, self::STATE_BURIED, self::STATE_DELAYED],
            'kick'    => [self::STATE_BURIED],
            'release' => [self::STATE_RESERVED],
        ];

        if (!in_array($currentState, $mapping[$action], true)) {
            throw new FlowException("Flow error, the message state '{$currentState}' cannot be '{$action}'.");
        }

        return $this;
    }

    /**
     * @param Message|string $mixed
     *
     * @return Payload
     * @throws QueueException
     */
    protected function payload($mixed)
    {
        $redis = $this->getRedis();
        $token = $mixed instanceof Message ? $mixed->getToken() : $mixed;
        $queue = $redis->hget($this->ns(self::NS_MESSAGE_TO_TUBE), $token);
        $state = $redis->hget($this->ns(self::NS_MESSAGE_TO_STATE), $token);

        if (empty($token) || empty($queue) || empty($state)) {
            throw new QueueException("Can not resolve message info, token: '{$token}', queue: '{$queue}', state '{$state}'");
        }

        return new Payload($token, $queue, $state);
    }

}
