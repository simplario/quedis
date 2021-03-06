<?php

namespace Simplario\Quedis\Tests;

use PHPUnit\Framework\TestCase;
use Simplario\Quedis\Interfaces\IteratorInterface;
use Simplario\Quedis\Iterator;
use Simplario\Quedis\Message;
use Simplario\Quedis\Queue;

/**
 * Class IteratorTest
 *
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
        $iterator = new Iterator($queue, 'dummy');

        $this->assertInstanceOf(Iterator::class, $iterator);
        $this->assertInstanceOf(IteratorInterface::class, $iterator);
    }


    /**
     * @return void
     */
    public function testIteratePopViaQueue()
    {
        $queue = $this->createQueue();

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'message 1');
        $originB = $queue->put(self::TEST_QUEUE_NAME, 'message 2');

        $result= [];
        $iterator = $queue->iterator(self::TEST_QUEUE_NAME, 'pop');

        foreach ($iterator as $index => $message){
            $result[] = $message;
        }

        $this->assertEquals($originA, $result[0]);
        $this->assertEquals($originB, $result[1]);
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
        $iterator = new Iterator($queue, self::TEST_QUEUE_NAME, 'pop', 0);

        foreach ($iterator as $index => $message){
            $result[] = $message;
        }

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
        $iterator = new Iterator($queue, self::TEST_QUEUE_NAME, 'reserve', 0);

        foreach ($iterator as $index => $message){
            $result[] = $message;

            $queue->delete($message);
        }

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
        $limit = 1;
        $iterator = new Iterator($queue, self::TEST_QUEUE_NAME, 'pop', 0);

        foreach ($iterator as $index => $message) {
            $result[] = $message;

            if ($index + 1 >= $limit) {
                break;
            }
        }

        $this->assertEquals($originA, $result[0]);
        $this->assertEquals(1, count($result));
    }

}