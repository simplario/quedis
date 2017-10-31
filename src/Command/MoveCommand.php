<?php

namespace Simplario\Quedis\Command;

use Simplario\Quedis\Queue;

/**
 * Class PutCommand
 *
 * @package Simplario\Quedis
 */
class MoveCommand extends AbstractCommand
{

    public function execute($mixed, $moveTo)
    {
        $payload = $this->payload($mixed);

        $this->checkMessageFlow($payload->getState(), $moveTo);

        $result = $this->queue->getRedis()->transaction(function ($tx) use ($payload, $moveTo) {
            /** @var $tx \Predis\Client */
            if ($moveTo === 'bury') {
                $tx->zrem($this->getKey($payload->getQueue(), Queue::STATE_RESERVED), $payload->getToken());
                $tx->zadd($this->getKey($payload->getQueue(), Queue::STATE_BURIED), time(), $payload->getToken());
                $tx->hset($this->ns(Queue::NS_MESSAGE_TO_STATE), $payload->getToken(), Queue::STATE_BURIED);
            } elseif ($moveTo === 'kick') {
                $tx->zrem($this->getKey($payload->getQueue(), Queue::STATE_BURIED), $payload->getToken());
                $tx->rpush($this->getKey($payload->getQueue(), Queue::STATE_READY), $payload->getToken());
                $tx->hset($this->ns(Queue::NS_MESSAGE_TO_STATE), $payload->getToken(), Queue::STATE_READY);
            }
        });

        $this->checkTransactionResult($result, $moveTo);

        return $this;
    }

}
