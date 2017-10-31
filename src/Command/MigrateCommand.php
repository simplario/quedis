<?php

namespace Simplario\Quedis\Command;

use Simplario\Quedis\Queue;

/**
 * Class PutCommand
 *
 * @package Simplario\Quedis
 */
class MigrateCommand extends AbstractCommand
{

    public function execute($queue = null)
    {
        if (null === $queue) {
            $queueList = $this->queue->getQueueList();
            foreach ($queueList as $queue) {
                $this->execute($queue);
            }

            return $this;
        }

        $keyReady = $this->getKey($queue, Queue::STATE_READY);
        $keyDelayed = $this->getKey($queue, Queue::STATE_DELAYED);

        $this->queue->getRedis()->transaction(['cas' => true, 'watch' => [$keyReady, $keyDelayed], 'retry' => 10],
            function ($tx) use ($queue, $keyReady, $keyDelayed) {

                /** @var $tx \Predis\Client */
                $time = time();

                // get expired jobs from "delayed queue"
                $messageTokenSet = $tx->zrangebyscore($keyDelayed, '-inf', $time);

                if (count($messageTokenSet) > 0) {
                    // remove jobs from "delayed queue"
                    $tx->multi();
                    $tx->zremrangebyscore($keyDelayed, '-inf', $time);
                    foreach ($messageTokenSet as $token) {
                        $tx->rpush($keyReady, $token);
                        $tx->hset($this->ns(Queue::NS_MESSAGE_TO_STATE), $token, Queue::STATE_READY);
                    }
                }
            });

        return $this;
    }

}
