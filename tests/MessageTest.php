<?php

namespace Simplario\Quedis\Tests;

use PHPUnit\Framework\TestCase;
use Simplario\Quedis\Interfaces\MessageInterface;
use Simplario\Quedis\Message;

/**
 * Class MessageTest
 * @package Simplario\Quedis\Tests
 */
class MessageTest extends TestCase
{
    /**
     * @return void
     */
    public function testInstance()
    {
        $message = new Message([1,2,3]);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertInstanceOf(MessageInterface::class, $message);
    }

    /**
     * @return void
     */
    public function testGettersSetters()
    {
        $message = new Message(['DATA'], 'TOKEN');

        $this->assertEquals(['DATA'], $message->getData());
        $this->assertEquals('TOKEN', $message->getToken());

        $message->setData('*** data ***');
        $message->setToken('*** token ***');

        $this->assertEquals('*** data ***', $message->getData());
        $this->assertEquals('*** token ***', $message->getToken());
    }

    /**
     * @return void
     */
    public function testEncodeDecode()
    {
        $message = new Message(['DATA'], 'TOKEN');

        $encoded = $message->encode();
        $msg = Message::decode($encoded);

        $this->assertEquals(['DATA'], $msg->getData());
        $this->assertEquals('TOKEN', $msg->getToken());
    }

}