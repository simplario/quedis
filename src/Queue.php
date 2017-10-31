<?php

namespace Simplario\Quedis;

use Simplario\Quedis\Command\DeleteCommand;
use Simplario\Quedis\Command\MigrateCommand;
use Simplario\Quedis\Command\MoveCommand;
use Simplario\Quedis\Command\PutCommand;
use Simplario\Quedis\Command\ReleaseCommand;
use Simplario\Quedis\Command\ReserveCommand;
use Simplario\Quedis\Exceptions\FlowException;
use Simplario\Quedis\Exceptions\QueueException;
use Simplario\Quedis\Interfaces\MessageInterface;
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

    const NS_QUEUE = 'queue';
    const NS_QUEUE_STOP = 'queue2stop';
    const NS_MESSAGE = 'message';
    const NS_MESSAGE_TO_QUEUE = 'message2queue';
    const NS_MESSAGE_TO_STATE = 'message2state';

    // State
    const STATE_READY = 'ready';
    const STATE_DELAYED = 'delayed';
    const STATE_RESERVED = 'reserved';
    const STATE_BURIED = 'buried';

    // Stats
    const STATS_QUEUES_LIST = 'queues-list';
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
     * @param mixed  $redis
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

    /**
     * @param string $queue
     * @param mixed  $data
     * @param int    $delay
     * @param string $priority
     *
     * @return MessageInterface
     * @throws \Exception
     */
    public function put($queue, $data, $delay = 0, $priority = self::PRIORITY_LOW)
    {
        $command = new PutCommand($this);
        $message = $command->execute($queue, $data, $delay, $priority);

        return $message;
    }

    /**
     * @param string        $queue
     * @param int           $timeout
     *
     * @return mixed|null|Queue
     */
    public function pop($queue, $timeout = 0)
    {
        $message = $this->reserve($queue, $timeout);

        if ($message !== null) {
            $this->delete($message);
        }

        return $message;
    }

    /**
     * @param string        $queue
     * @param int           $timeout
     *
     * @return MessageInterface|null
     */
    public function reserve($queue, $timeout = 0)
    {
        $command = new ReserveCommand($this);

        return $command->execute($queue, $timeout);
    }


    /**
     * @param string|MessageInterface $mixed
     *
     * @return $this
     * @throws QueueException
     */
    public function delete($mixed)
    {
        $command = new DeleteCommand($this);
        $command->execute($mixed);

        return $this;
    }

    /**
     * @param MessageInterface|string $mixed
     * @param int $delay
     *
     * @return $this
     * @throws FlowException
     * @throws QueueException
     */
    public function release($mixed, $delay = 0)
    {
        $command = new ReleaseCommand($this);
        $command->execute($mixed, $delay);

        return $this;
    }

    /**
     * @param MessageInterface|string $mixed
     *
     * @return $this
     * @throws QueueException
     */
    public function kick($mixed)
    {
        return $this->moveMessage($mixed, 'kick');
    }

    /**
     * @param string|MessageInterface $mixed
     *
     * @return $this
     * @throws QueueException
     */
    public function bury($mixed)
    {
        return $this->moveMessage($mixed, 'bury');
    }

    /**
     * @param MessageInterface|string $mixed
     * @param string                  $moveTo
     *
     * @return $this
     * @throws QueueException
     */
    protected function moveMessage($mixed, $moveTo)
    {
        $command = new MoveCommand($this);
        $command->execute($mixed, $moveTo);

        return $this;
    }

    /**
     * @return array
     */
    public function getQueueList()
    {
        $redis = $this->getRedis();
        $queueList = $redis->hgetall($this->ns(self::NS_MESSAGE_TO_QUEUE));
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

        $command = new MigrateCommand($this);
        $command->execute($queue);

        return $this;
    }


    /**
     * @param string $queue
     * @param string $strategy
     * @param int    $timeout
     *
     * @return Iterator
     */
    public function iterator($queue, $strategy = 'pop', $timeout = 0)
    {
        return new Iterator($this, $queue, $strategy, $timeout);
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
     * @param string $queue
     *
     * @return bool
     */
    public function isStop($queue)
    {
        return (bool)$this->getRedis()->hexists($this->ns(self::NS_QUEUE_STOP), $queue);
    }

    /**
     * @param string $queue
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
     * @param string $queue
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
     * @param null|string $queue
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
     * @param string $type
     *
     * @return string
     */
    protected function ns($type)
    {
        return implode(':', [$this->namespace, $type]);
    }


    /**
     * @param string $queue
     * @param string $state
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
     * @param string $currentState
     * @param string $action
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
     * @param mixed  $result
     * @param string $action
     *
     * @return $this
     * @throws QueueException
     */
    protected function checkTransactionResult($result, $action)
    {
        if (in_array(false, (array) $result, true)) {
            throw new QueueException("Transaction '{$action}' error.");
        }

        return $this;
    }

    /**
     * @param string|MessageInterface $mixed
     *
     * @return Payload
     * @throws QueueException
     */
    protected function payload($mixed)
    {
        $redis = $this->getRedis();
        $token = $mixed instanceof MessageInterface ? $mixed->getToken() : $mixed;
        $queue = $redis->hget($this->ns(self::NS_MESSAGE_TO_QUEUE), $token);
        $state = $redis->hget($this->ns(self::NS_MESSAGE_TO_STATE), $token);

        return new Payload($token, $queue, $state);
    }

}
