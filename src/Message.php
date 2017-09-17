<?php

namespace Simplario\Quedis;

use Simplario\Quedis\Interfaces\MessageInterface;

/**
 * Class Message
 * @package Simplario\Quedis
 */
class Message implements MessageInterface
{
    /**
     * @var null|string
     */
    protected $token;
    /**
     * @var mixed
     */
    protected $data;

    /**
     * Message constructor.
     * @param mixed $data
     * @param null $token
     */
    public function __construct($data, $token = null)
    {
        $this->data = $data;
        $this->token = $token !== null ? $token : $this->generateToken();
    }

    protected function generateToken()
    {
        return uniqid() . '-' . time();
    }

    /**
     * @return string
     */
    public function encode()
    {
        return serialize([$this->getData(), $this->getToken()]);
    }

    /**
     * @param $encoded
     * @return static
     */
    public static function decode($encoded)
    {
        $encoded = unserialize($encoded);

        return new static($encoded[0], $encoded[1]);
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $token
     * @return $this
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }
}

