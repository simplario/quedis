<?php

namespace Simplario\Quedis\Interfaces;

/**
 * Interface IteratorInterface
 * @package Simplario\Quedis\Interfaces
 */
interface IteratorInterface extends \Iterator
{
    /**
     * Iterator constructor.
     *
     * @param QueueInterface $queue
     * @param string         $queueName
     * @param string         $strategy
     * @param int            $timeout
     */
    public function __construct(QueueInterface $queue, $queueName, $strategy = 'pop', $timeout = 0);

    /**
     * @param QueueInterface $queue
     *
     * @return $this
     */
    public function setQueue(QueueInterface $queue);

    /**
     * @param $queueName
     *
     * @return $this
     */
    public function setQueueName($queueName);

    /**
     * @param $strategy
     *
     * @return $this
     */
    public function setStrategy($strategy);

    /**
     * @param $timeout
     *
     * @return $this
     */
    public function setTimeout($timeout);

}
