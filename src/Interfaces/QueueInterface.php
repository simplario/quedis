<?php

namespace Simplario\Quedis\Interfaces;

use Simplario\Quedis\Message;

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
     * @return Message|null
     */
    public function pop($queue, $timeout = 0);

    /**
     * @param               $queue
     * @param               $timeout
     *
     * @return Message|null
     */
    public function reserve($queue, $timeout);

    /**
     * @param string|Message $mixed
     *
     * @return boolean
     */
    public function bury($mixed);

    /**
     * @param string|Message $mixed
     *
     * @return boolean
     */
    public function delete($mixed);

    /**
     * @param string|Message $mixed
     *
     * @return boolean
     */
    public function kick($mixed);

    /**
     * @param string $queue
     *
     * @return boolean
     */
    public function stop($queue);

    /**
     * @param string $queue
     *
     * @return boolean
     */
    public function start($queue);

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
}
