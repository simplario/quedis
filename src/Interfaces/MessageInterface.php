<?php

namespace Simplario\Quedis\Interfaces;

/**
 * Interface MessageInterface
 *
 * @package Simplario\Quedis\Interfaces
 */
interface MessageInterface
{
    /**
     * MessageInterface constructor.
     *
     * @param mixed $data
     * @param null  $token
     */
    public function __construct($data, $token = null);

    /**
     * @return mixed
     */
    public function getData();

    /**
     * @param $data
     *
     * @return $this
     */
    public function setData($data);

    /**
     * @return string
     */
    public function getToken();

    /**
     * @param string $token
     *
     * @return $this
     */
    public function setToken($token);

    /**
     * @return string
     */
    public function encode();

    /**
     * @param $encoded
     *
     * @return static
     */
    public static function decode($encoded);

}
