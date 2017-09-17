<?php

namespace Simplario\Quedis\Tests;

use PHPUnit\Framework\TestCase;
use Simplario\Quedis\Exceptions\FlowException;
use Simplario\Quedis\Message;
use Simplario\Quedis\Queue;

/**
 * Class FlowTest
 * @package Simplario\Quedis\Tests
 */
class FlowTest extends TestCase
{

    const TEST_QUEUE_NAMESPACE = 'test-redis-queue-namespace';
    const TEST_QUEUE_NAME = 'test-queue-name-flow';

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
    public function testQueuePutReserveWithCallback()
    {
        $queue = $this->createQueue();

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'aaa');
        $originB = $queue->put(self::TEST_QUEUE_NAME, 'bbb');

        // in other process

        $fromQueueSet = [];
        $queue->reserve(self::TEST_QUEUE_NAME, 0, function (Message $message, Queue $queue) use (&$fromQueueSet) {

            // do something with message ...

            $fromQueueSet[] = $message;
            $queue->delete($message);
        });

        // tests

        $this->assertEquals($originA, $fromQueueSet[0]);
        $this->assertEquals($originB, $fromQueueSet[1]);

        $stats = $queue->stats();
        $this->assertEquals(0, $stats[Queue::STATS_MESSAGE_TOTAL]);
    }


    /**
     * @return void
     */
    public function testQueuePutPopWithCallback()
    {
        $queue = $this->createQueue();

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'aaa');
        $originB = $queue->put(self::TEST_QUEUE_NAME, 'bbb');

        // in other process

        $fromQueueSet = [];
        $queue->pop(self::TEST_QUEUE_NAME, 0, function (Message $message, Queue $queue) use (&$fromQueueSet) {

            // do something with message ...

            $fromQueueSet[] = $message;
        });

        // tests

        $this->assertEquals($originA, $fromQueueSet[0]);
        $this->assertEquals($originB, $fromQueueSet[1]);

        $stats = $queue->stats();
        $this->assertEquals(0, $stats[Queue::STATS_MESSAGE_TOTAL]);
    }

    /**
     * @return void
     */
    public function testQueuePutPop()
    {
        $queue = $this->createQueue();

        $origin = $queue->put(self::TEST_QUEUE_NAME, 'aaa');

        // in other process

        $message = $queue->pop(self::TEST_QUEUE_NAME, 0);

        // tests

        $this->assertEquals($origin, $message);
        $this->assertEquals(0, $queue->stats()[Queue::STATS_MESSAGE_TOTAL]);
    }


    /**
     * @return void
     */
    public function testQueuePutReserveDelete()
    {
        $queue = $this->createQueue();

        $origin = $queue->put(self::TEST_QUEUE_NAME, 'aaa');

        // in other process

        $message = $queue->reserve(self::TEST_QUEUE_NAME);

        $queue->delete($message);

        // tests

        $this->assertEquals($origin, $message);
    }


    /**
     * @return void
     */
    public function testQueuePutReserveReleaseReserveDelete()
    {
        $queue = $this->createQueue();

        $origin = $queue->put(self::TEST_QUEUE_NAME, 'aaa');

        // in other process

        $message = $queue->reserve(self::TEST_QUEUE_NAME);
        $queue->release($message);

        // next time

        $message = $queue->reserve(self::TEST_QUEUE_NAME);
        $queue->delete($message);

        // tests

        $this->assertEquals($origin, $message);
    }

    /**
     * @return void
     */
    public function testQueuePutReserveBuryDelete()
    {
        $queue = $this->createQueue();

        $origin = $queue->put(self::TEST_QUEUE_NAME, 'aaa');

        // in other process

        $message = $queue->reserve(self::TEST_QUEUE_NAME);
        $queue->bury($message);
        $queue->delete($message);

        // tests

        $this->assertEquals($origin, $message);
    }

    /**
     * @return void
     */
    public function testQueuePutReserveBuryKickReserveDelete()
    {
        $queue = $this->createQueue();

        $origin = $queue->put(self::TEST_QUEUE_NAME, 'aaa');

        // in other process

        $message = $queue->reserve(self::TEST_QUEUE_NAME);
        $queue->bury($message);

        // next time
        $queue->kick($message);

        $message = $queue->reserve(self::TEST_QUEUE_NAME);
        $queue->delete($message);

        // tests

        $this->assertEquals($origin, $message);
    }


    /**
     * @return void
     */
    public function testQueuePutWithDelayReserveDelete()
    {
        $queue = $this->createQueue();

        $origin = $queue->put(self::TEST_QUEUE_NAME, 'aaa', 1);

        // in other process

        $null = $queue->reserve(self::TEST_QUEUE_NAME);
        sleep(1);
        $message = $queue->reserve(self::TEST_QUEUE_NAME);
        $queue->delete($message);

        // tests

        $this->assertEquals(null, $null);
        $this->assertEquals($origin, $message);
    }

    /**
     * @return void
     */
    public function testQueuePutWithDelayPop()
    {
        $queue = $this->createQueue();

        $origin = $queue->put(self::TEST_QUEUE_NAME, 'aaa', 1);

        // in other process

        $null = $queue->pop(self::TEST_QUEUE_NAME);
        sleep(1);
        $message = $queue->pop(self::TEST_QUEUE_NAME);

        // tests

        $this->assertEquals(null, $null);
        $this->assertEquals($origin, $message);
        $this->assertEquals(0, $queue->stats()[Queue::STATS_MESSAGE_TOTAL]);
    }

    /**
     * @return void
     */
    public function testQueuePutKickException()
    {
        $queue = $this->createQueue();

        $message = $queue->put(self::TEST_QUEUE_NAME, 'aaa');

        // in other process

        $this->expectException(FlowException::class);

        $queue->kick($message);
    }

    /**
     * @return void
     */
    public function testQueuePutBuryException()
    {
        $queue = $this->createQueue();

        $message = $queue->put(self::TEST_QUEUE_NAME, 'aaa');

        // in other process

        $this->expectException(FlowException::class);

        $queue->bury($message);
    }

    /**
     * @return void
     */
    public function testQueuePutReleaseException()
    {
        $queue = $this->createQueue();

        $message = $queue->put(self::TEST_QUEUE_NAME, 'aaa');

        // in other process

        $this->expectException(FlowException::class);

        $queue->release($message);
    }

    /**
     * @return void
     */
    public function testQueuePutDeleteException()
    {
        $queue = $this->createQueue();

        $message = $queue->put(self::TEST_QUEUE_NAME, 'aaa');

        // in other process

        $this->expectException(FlowException::class);

        $queue->delete($message);
    }


    /**
     * @return void
     */
    public function testQueuePutReserveKickException()
    {
        $queue = $this->createQueue();

        $origin = $queue->put(self::TEST_QUEUE_NAME, 'aaa');
        $message = $queue->reserve(self::TEST_QUEUE_NAME);

        // in other process

        $this->expectException(FlowException::class);

        $queue->kick($message);
    }


    /**
     * @return void
     */
    public function testQueuePutWithPriorityPop()
    {
        $queue = $this->createQueue();

        $originA = $queue->put(self::TEST_QUEUE_NAME, 'aaa',0, Queue::PRIORITY_LOW);
        $originB = $queue->put(self::TEST_QUEUE_NAME, 'bbb',0, Queue::PRIORITY_HIGH);
        $originC = $queue->put(self::TEST_QUEUE_NAME, 'ccc',0, Queue::PRIORITY_LOW);

        $messageB = $queue->pop(self::TEST_QUEUE_NAME);
        $this->assertEquals($originB, $messageB);

        $messageA = $queue->pop(self::TEST_QUEUE_NAME);
        $this->assertEquals($originA, $messageA);

        $messageC = $queue->pop(self::TEST_QUEUE_NAME);
        $this->assertEquals($originC, $messageC);
    }

    /**
     * @return void
     */
    public function testQueuePutReserveWithTimeout()
    {
        $queue = $this->createQueue();

        $origin = $queue->put(self::TEST_QUEUE_NAME, 'aaa');

        $message = $queue->reserve(self::TEST_QUEUE_NAME, 99);
        $this->assertEquals($origin, $message);

        $queue->delete($message);
    }

    /**
     * @return void
     */
    public function testQueuePutPopWithTimeout()
    {
        $queue = $this->createQueue();

        $origin = $queue->put(self::TEST_QUEUE_NAME, 'aaa');

        $message = $queue->pop(self::TEST_QUEUE_NAME, 99);
        $this->assertEquals($origin, $message);
    }

    /**
     * @return void
     */
    public function testQueuePutDifferentMessages()
    {
        $queue = $this->createQueue();

        $variants = [
            'aaa',
            123,
            new Message('xxx'),
            new \stdClass(),
            [[[[[[[[[[[[[[[[[[[123]]]]]]]]]]]]]]]]]]],
        ];

        foreach($variants as $index => $item){
            $origin = $queue->put(self::TEST_QUEUE_NAME, $item);
            $message = $queue->pop(self::TEST_QUEUE_NAME);

            $item = $item instanceof Message ? $item->getData() : $item;

            $this->assertEquals($item, $message->getData());
            $this->assertEquals($item, $origin->getData());
        }
    }

    /**
     * @return void
     */
    public function testMass()
    {
        $queue = $this->createQueue();

        $i = $total = 100;
        while($i--){
            $queue->put(self::TEST_QUEUE_NAME, [[[[[[[[[[$i]]]]]]]]]]);
        }

        $t = 0;
        while($message = $queue->pop(self::TEST_QUEUE_NAME)){
            $t++;
        }

        $this->assertEquals($total, $t);
    }

}