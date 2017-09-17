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
     * @param string $tube
     * @param        $data
     * @param        $delay
     * @param        $priority
     *
     * @return string
     */
    public function put($tube, $data, $delay, $priority);

    /**
     * @param               $tube
     * @param int           $timeout
     * @param callable|null $callback
     *
     * @return mixed
     */
    public function pop($tube, $timeout = 0, callable $callback = null);

    /**
     * @param               $tube
     * @param               $timeout
     * @param callable|null $callback
     *
     * @return mixed
     */
    public function reserve($tube, $timeout, callable $callback = null);

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
