<?php

namespace Simplario\Quedis\Tests;

use PHPUnit\Framework\TestCase;
use Simplario\Quedis\Exceptions\QueueException;
use Simplario\Quedis\Interfaces\QueueInterface;
use Simplario\Quedis\Queue;

/**
 * Class QueueTest
 * @package Simplario\Quedis\Tests
 */
class QueueTest extends TestCase
{

    const TEST_QUEUE_NAMESPACE = 'test-redis-queue-namespace';
    const TEST_QUEUE_NAME = 'test-queue-name';

    /**
     * @return Queue
     */
    protected function createQueue()
    {
        $redis = new \Predis\Client();

        $queue = new Queue($redis, self::TEST_QUEUE_NAMESPACE);
        $queue->clean();

        return $queue;
    }

    /**
     * @return void
     */
    public function testInterface()
    {
        $queue = $this->createQueue();

        $this->assertInstanceOf(Queue::class, $queue);
        $this->assertInstanceOf(QueueInterface::class, $queue);
    }

    /**
     * @return void
     */
    public function testNamespace()
    {
        $queue = $this->createQueue();

        $this->assertEquals(self::TEST_QUEUE_NAMESPACE, $queue->getNamespace());
    }


    /**
     * @return void
     */
    public function testRedis()
    {
        $queue = $this->createQueue();

        $this->assertEquals(true, is_object($queue->getRedis()));
    }


    /**
     * @return void
     */
    public function testStartStop()
    {
        $queue = $this->createQueue();

        $this->assertFalse(
            $queue->isStop(self::TEST_QUEUE_NAME)
        );

        $queue->stop(self::TEST_QUEUE_NAME);

        $this->assertTrue(
            $queue->isStop(self::TEST_QUEUE_NAME)
        );

        $queue->start(self::TEST_QUEUE_NAME);

        $this->assertFalse(
            $queue->isStop(self::TEST_QUEUE_NAME)
        );
    }


    /**
     * @return void
     */
    public function testStatsSingleQueue()
    {
        $queue = $this->createQueue();

        $stats = $queue->stats(self::TEST_QUEUE_NAME);
        $this->assertEquals(0, $stats[Queue::STATS_MESSAGE_TOTAL]);

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'message 1');
        $originB = $queue->put(self::TEST_QUEUE_NAME, 'message 2' , (new \DateTime())->modify('+1 day'));

        $stats = $queue->stats(self::TEST_QUEUE_NAME);
        $this->assertEquals(1, $stats[Queue::STATS_MESSAGE_READY]);
        $this->assertEquals(1, $stats[Queue::STATS_MESSAGE_DELAYED]);
        $this->assertEquals(2, $stats[Queue::STATS_MESSAGE_TOTAL]);

        $messageA = $queue->reserve(self::TEST_QUEUE_NAME, 0);

        $stats = $queue->stats(self::TEST_QUEUE_NAME);
        $this->assertEquals(1, $stats[Queue::STATS_MESSAGE_RESERVED]);
        $this->assertEquals(1, $stats[Queue::STATS_MESSAGE_DELAYED]);
        $this->assertEquals(2, $stats[Queue::STATS_MESSAGE_TOTAL]);


        $queue->delete($messageA);

        $stats = $queue->stats(self::TEST_QUEUE_NAME);
        $this->assertEquals(1, $stats[Queue::STATS_MESSAGE_DELAYED]);
        $this->assertEquals(1, $stats[Queue::STATS_MESSAGE_TOTAL]);


        $queue->delete($originB);

        $stats = $queue->stats(self::TEST_QUEUE_NAME);
        $this->assertEquals(0, $stats[Queue::STATS_MESSAGE_TOTAL]);
    }

    /**
     * @return void
     */
    public function testStats()
    {
        $queue = $this->createQueue();

        $stats = $queue->stats();
        $this->assertEquals(0, $stats[Queue::STATS_MESSAGE_TOTAL]);

        $originA = $queue->put(self::TEST_QUEUE_NAME . '-111', 'message 1');
        $originB = $queue->put(self::TEST_QUEUE_NAME. '-222', 'message 2' , 10);

        $stats = $queue->stats();
        $this->assertEquals(1, $stats[Queue::STATS_MESSAGE_READY]);
        $this->assertEquals(1, $stats[Queue::STATS_MESSAGE_DELAYED]);
        $this->assertEquals(2, $stats[Queue::STATS_MESSAGE_TOTAL]);

    }

    /**
     * @return void
     */
    public function testMigrate()
    {
        $queue = $this->createQueue();

        $stats = $queue->stats();
        $this->assertEquals(0, $stats[Queue::STATS_MESSAGE_TOTAL]);

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'message 1', 1);
        $originB = $queue->put(self::TEST_QUEUE_NAME, 'message 2', 1);

        $stats = $queue->stats(self::TEST_QUEUE_NAME);
        $this->assertEquals(2, $stats[Queue::STATS_MESSAGE_DELAYED]);

        sleep(1);
        $queue->migrate();

        $stats = $queue->stats(self::TEST_QUEUE_NAME);
        $this->assertEquals(2, $stats[Queue::STATS_MESSAGE_READY]);


    }

    /**
     * @return void
     */
    public function testSizeException()
    {
        $queue = $this->createQueue();

        $this->expectException(QueueException::class);

        $size = $queue->size(self::TEST_QUEUE_NAME, '### BAD STATE ####');

    }

    /**
     * @return void
     */
    public function testReserveWhenStop()
    {
        $queue = $this->createQueue();

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'message 1', 1);

        $queue->stop(self::TEST_QUEUE_NAME);

        $null = $queue->reserve(self::TEST_QUEUE_NAME);

        $this->assertNull($null);
    }


    /**
     * @return void
     */
    public function testBadIdeaCheckTransactionResult()
    {
        if (version_compare(PHP_VERSION, '7.0', '>=')) {

            $redis = new \Predis\Client();
            $class = new class('Anonymous') extends Queue
            {
                public function PublicCheckTransactionResult($result, $action)
                {
                    return $this->checkTransactionResult($result, $action);
                }
            };

            $queue = new $class($redis, self::TEST_QUEUE_NAMESPACE);
            $queue->clean();

            $instance = $queue->PublicCheckTransactionResult([true, true, true], 'dummy action 1');

            $this->assertEquals($queue, $instance);

            $this->expectException(QueueException::class);

            $queue->PublicCheckTransactionResult([true, true, false], 'dummy action 2');

        } else {
            $this->assertEquals(true, true);
        }

    }

}