<?php

namespace Simplario\Quedis\Tests;

use PHPUnit\Framework\TestCase;
use Simplario\Quedis\Exceptions\QueueException;
use Simplario\Quedis\Interfaces\IteratorInterface;
use Simplario\Quedis\Interfaces\QueueInterface;
use Simplario\Quedis\Iterator;
use Simplario\Quedis\Message;
use Simplario\Quedis\Queue;

/**
 * Class IteratorTest
 * @package Simplario\Quedis\Tests
 */
class IteratorTest extends TestCase
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
        $iterator = new Iterator($queue, []);

        $this->assertInstanceOf(Iterator::class, $iterator);
        $this->assertInstanceOf(IteratorInterface::class, $iterator);
    }


    /**
     * @return void
     */
    public function testIteratePopWay()
    {
        $queue = $this->createQueue();

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'message 1');
        $originB = $queue->put(self::TEST_QUEUE_NAME, 'message 2');


        $result = [];

        $iterator = new Iterator($queue, [
            'queue'         => self::TEST_QUEUE_NAME,
            'sleep'         => 0,
            'timeout'       => 0,
            'strategy'      => 'pop',
            'limit-message' => 100,
            'limit-loop'    => 10,
        ]);


        $iterator->each(function(Message $message, Queue $queue) use (&$result){
            $result[] = $message;
        });

        $this->assertEquals($originA, $result[0]);
        $this->assertEquals($originB, $result[1]);
    }

    /**
     * @return void
     */
    public function testIterateReserveWay()
    {
        $queue = $this->createQueue();

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'message 1');
        $originB = $queue->put(self::TEST_QUEUE_NAME, 'message 2');


        $result = [];

        $iterator = new Iterator($queue, [
            'queue'         => self::TEST_QUEUE_NAME,
            'sleep'         => 0,
            'timeout'       => 0,
            'strategy'      => 'reserve',
            'limit-message' => 100,
            'limit-loop'    => 10,
        ]);


        $iterator->each(function(Message $message, Queue $queue) use (&$result){
            $result[] = $message;

            $queue->delete($message);
        });

        $this->assertEquals($originA, $result[0]);
        $this->assertEquals($originB, $result[1]);
    }



    /**
     * @return void
     */
    public function testIteratePopWayLimitMessage()
    {
        $queue = $this->createQueue();

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'message 1');
        $originB = $queue->put(self::TEST_QUEUE_NAME, 'message 2');


        $result = [];

        $iterator = new Iterator($queue, [
            'queue'         => self::TEST_QUEUE_NAME,
            'timeout'       => 0,
            'strategy'      => 'pop',
            'limit-message' => 1,
        ]);


        $iterator->each(function(Message $message, Queue $queue) use (&$result){
            $result[] = $message;
        });

        $this->assertEquals($originA, $result[0]);
        $this->assertEquals(1, count($result));
    }


    /**
     * @return void
     */
    public function testIteratePopWayLimitLoop()
    {
        $queue = $this->createQueue();

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'message 1');
        $originB = $queue->put(self::TEST_QUEUE_NAME, 'message 2');


        $result = [];

        $iterator = new Iterator($queue, [
            'queue'         => self::TEST_QUEUE_NAME,
            'timeout'       => 0,
            'strategy'      => 'pop',
            'limit-loop'    => 1,
        ]);


        $iterator->each(function(Message $message, Queue $queue) use (&$result){
            $result[] = $message;
        });

        $this->assertEquals($originA, $result[0]);
        $this->assertEquals(1, count($result));
    }



    /**
     * @return void
     */
    public function testIteratePopWaySleep()
    {
        $queue = $this->createQueue();

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'message 1');
        $originB = $queue->put(self::TEST_QUEUE_NAME, 'message 2');


        $time = time();
        $result = [];

        $iterator = new Iterator($queue, [
            'queue'    => self::TEST_QUEUE_NAME,
            'timeout'  => 0,
            'strategy' => 'pop',
            'sleep'    => 1,
        ]);


        $iterator->each(function(Message $message, Queue $queue) use (&$result){
            $result[] = $message;
        });

        $this->assertEquals($originA, $result[0]);
        $this->assertEquals($originB, $result[1]);
        $this->assertTrue(time() > $time);
    }
}