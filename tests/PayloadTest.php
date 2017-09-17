<?php

namespace Simplario\Quedis\Tests;

use PHPUnit\Framework\TestCase;
use Simplario\Quedis\Interfaces\PayloadInterface;
use Simplario\Quedis\Payload;

/**
 * Class PayloadTest
 * @package Simplario\Quedis\Tests
 */
class PayloadTest extends TestCase
{

    /**
     * @return void
     */
    public function testInstance()
    {
        $payload = new Payload('token123', 'queue123', 'state123');

        $this->assertInstanceOf(Payload::class, $payload);
        $this->assertInstanceOf(PayloadInterface::class, $payload);
    }

    /**
     * @return void
     */
    public function testGetterSetter()
    {
        $payload = new Payload('token123', 'queue123', 'state123');

        $this->assertEquals('token123', $payload->getToken());
        $this->assertEquals('queue123', $payload->getQueue());
        $this->assertEquals('state123', $payload->getState());
    }
}