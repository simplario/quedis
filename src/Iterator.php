<?php

namespace Simplario\Quedis;

use Simplario\Quedis\Interfaces\IteratorInterface;
use Simplario\Quedis\Interfaces\MessageInterface;
use Simplario\Quedis\Interfaces\QueueInterface;

/**
 * Class Iterator
 *
 * @package Simplario\Quedis
 */
class Iterator implements IteratorInterface
{
    /**
     * @var QueueInterface
     */
    protected $queue;

    /**
     * @var string
     */
    protected $queueName;

    /**
     * @var string
     */
    protected $strategy;

    /**
     * @var int
     */
    protected $timeout;

    /**
     * @var int
     */
    protected $index = 0;

    /**
     * @var MessageInterface
     */
    protected $lastMessage;

    /**
     * Iterator constructor.
     *
     * @param QueueInterface $queue
     * @param string         $queueName
     * @param string         $strategy
     * @param int            $timeout
     */
    public function __construct(QueueInterface $queue, $queueName, $strategy = 'pop', $timeout = 0)
    {
        $this
            ->setQueue($queue)
            ->setQueueName($queueName)
            ->setStrategy($strategy)
            ->setTimeout($timeout);
    }

    /**
     * @param QueueInterface $queue
     *
     * @return $this
     */
    public function setQueue(QueueInterface $queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * @param $queueName
     *
     * @return $this
     */
    public function setQueueName($queueName)
    {
        $this->queueName = $queueName;

        return $this;
    }

    /**
     * @param $strategy
     *
     * @return $this
     */
    public function setStrategy($strategy)
    {
        $this->strategy = in_array($strategy, ['pop', 'reserve']) ? $strategy : 'pop';

        return $this;
    }

    /**
     * @param $timeout
     *
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @return null|MessageInterface
     */
    protected function getMessage()
    {
        return $this->queue->{$this->strategy}($this->queueName, $this->timeout);
    }

    // implement \Iterator methods ==============================================

    /**
     * @return MessageInterface
     */
    public function current()
    {
        return $this->lastMessage;
    }

    /**
     * @return int
     */
    public function key()
    {
        return $this->index;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        $this->lastMessage = $this->getMessage();

        return $this->lastMessage instanceof MessageInterface;
    }

    /**
     * @return void
     */
    public function rewind()
    {
        $this->index = 0;
    }

    /**
     * @return void
     */
    public function next()
    {
        $this->index++;
    }
}