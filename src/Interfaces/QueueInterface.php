<?php

namespace Simplario\Quedis\Interfaces;

/**
 * Interface QueueInterface
 *
 * @package Simplario\Quedis\Interfaces
 */
interface QueueInterface
{
    /**
     * QueueInterface constructor.
     *
     * @param        $redis
     * @param string $namespace
     */
    public function __construct($redis, $namespace);

    /**
     * @param string $queue
     * @param        $data
     * @param        $delay
     * @param        $priority
     *
     * @return string
     */
    public function put($queue, $data, $delay, $priority);

    /**
     * @param               $queue
     * @param int           $timeout
     *
     * @return null|MessageInterface
     */
    public function pop($queue, $timeout = 0);

    /**
     * @param               $queue
     * @param               $timeout
     *
     * @return null|MessageInterface
     */
    public function reserve($queue, $timeout);

    /**
     * @param string|MessageInterface $mixed
     *
     * @return boolean
     */
    public function bury($mixed);

    /**
     * @param string|MessageInterface $mixed
     *
     * @return boolean
     */
    public function delete($mixed);

    /**
     * @param MessageInterface|string $mixed
     *
     * @return boolean
     */
    public function kick($mixed);

    /**
     * @param string $queue
     *
     * @return $this
     */
    public function stop($queue);

    /**
     * @param string $queue
     *
     * @return $this
     */
    public function start($queue);

    /**
     * @param string $queue
     *
     * @return bool
     */
    public function isStop($queue);

    /**
     * @return array
     */
    public function stats();

    /**
     * @param string $queue
     *
     * @return mixed
     */
    public function clean($queue = null);

    /**
     * @param string $queue
     * @param array  $options
     *
     * @return IteratorInterface
     */
    public function iterator($queue, array $options = []);

}
