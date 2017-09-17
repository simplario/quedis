<?php

namespace Simplario\Quedis;

use Simplario\Quedis\Interfaces\PayloadInterface;

/**
 * Class Payload
 * @package Simplario\Quedis
 */
class Payload implements PayloadInterface
{
    /**
     * @var string
     */
    protected $token;
    /**
     * @var string
     */
    protected $queue;
    /**
     * @var string
     */
    protected $state;

    /**
     * Payload constructor.
     * @param string|Message $token
     * @param string $queue
     * @param string $state
     */
    public function __construct($token, $queue, $state)
    {
        $this->token = $token instanceof Message ? $token->getToken() : $token;
        $this->queue = $queue;
        $this->state = $state;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }


}
