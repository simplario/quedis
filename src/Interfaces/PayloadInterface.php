<?php

namespace Simplario\Quedis\Interfaces;

/**
 * Interface PayloadInterface
 *
 * @package Simplario\Quedis\Interfaces
 */
interface PayloadInterface
{
    /**
     * PayloadInterface constructor.
     *
     * @param $token
     * @param $queue
     * @param $state
     */
    public function __construct($token, $queue, $state);

    /**
     * @return string
     */
    public function getToken();

    /**
     * @return string
     */
    public function getQueue();

    /**
     * @return string
     */
    public function getState();
}
