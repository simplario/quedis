<?php

namespace Simplario\Quedis\Command;

use Simplario\Quedis\Exceptions\FlowException;
use Simplario\Quedis\Exceptions\QueueException;
use Simplario\Quedis\Interfaces\MessageInterface;
use Simplario\Quedis\Message;
use Simplario\Quedis\Payload;
use Simplario\Quedis\Queue;

/**
 * Class AbstractCommand
 *
 * @package Simplario\Quedis\Command
 */
class AbstractCommand
{

    /**
     * @var Queue
     */
    protected $queue;

    /**
     * PutCommand constructor.
     *
     * @param Queue $queue
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }





    /**
     * @param mixed $mixed
     *
     * @return MessageInterface
     */
    protected function createMessage($mixed)
    {
        if ($mixed instanceof MessageInterface) {
            return $mixed;
        }

        return new Message($mixed);
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function ns($type)
    {
        return implode(':', [$this->queue->getNamespace(), $type]);
    }


    /**
     * @param string $queue
     * @param string $state
     *
     * @return string
     */
    protected function getKey($queue, $state)
    {
        return implode(':', [$this->ns(Queue::NS_QUEUE), $queue, $state]);
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
            'bury'    => [Queue::STATE_RESERVED],
            'delete'  => [Queue::STATE_RESERVED, Queue::STATE_BURIED, Queue::STATE_DELAYED],
            'kick'    => [Queue::STATE_BURIED],
            'release' => [Queue::STATE_RESERVED],
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
        $redis = $this->queue->getRedis();
        $token = $mixed instanceof MessageInterface ? $mixed->getToken() : $mixed;
        $queue = $redis->hget($this->ns(Queue::NS_MESSAGE_TO_QUEUE), $token);
        $state = $redis->hget($this->ns(Queue::NS_MESSAGE_TO_STATE), $token);

        return new Payload($token, $queue, $state);
    }

    /**
     * @param string $queue
     * @param int    $timeout
     *
     * @return null|string
     */
    protected function reserveToken($queue, $timeout = 0)
    {
        $redis = $this->queue->getRedis();

        if ($timeout > 0) {
            // example return [ '0' => queue name , '1' => jobId ]
            $token = $redis->blpop([$this->getKey($queue, Queue::STATE_READY)], $timeout);
            $token = (is_array($token)) ? $token[1] : null;
        } else {
            $token = $redis->lpop($this->getKey($queue, Queue::STATE_READY));
        }

        return $token;
    }

    /**
     * @param string|null $token
     *
     * @return null|MessageInterface
     */
    protected function restoreMessage($token)
    {
        if (is_null($token)) {
            return null;
        }

        $encoded = $this->queue->getRedis()->hget($this->ns(Queue::NS_MESSAGE), $token);
        $message = Message::decode($encoded);

        return $message;
    }
}
