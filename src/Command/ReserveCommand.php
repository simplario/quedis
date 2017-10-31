<?php

namespace Simplario\Quedis\Command;

use Simplario\Quedis\Queue;

/**
 * Class PutCommand
 *
 * @package Simplario\Quedis
 */
class ReserveCommand extends AbstractCommand
{

    public function execute($queue, $timeout = 0)
    {
        if ($this->queue->isStop($queue)) {
            return null;
        }

        $this->queue->migrate($queue);

        $token = $this->reserveToken($queue, $timeout);
        $message = $this->restoreMessage($token);

        if($message === null){
            return null;
        }

        $this->queue->getRedis()->transaction(function ($tx) use ($queue, $token) {
            /** @var $tx \Predis\Client */
            $tx->zadd($this->getKey($queue, Queue::STATE_RESERVED), time(), $token);
            $tx->hset($this->ns(Queue::NS_MESSAGE_TO_STATE), $token, Queue::STATE_RESERVED);
        });

        return $message;
    }

}
