<?php

namespace Simplario\Quedis\Command;

use Simplario\Quedis\Queue;

/**
 * Class PutCommand
 *
 * @package Simplario\Quedis
 */
class DeleteCommand extends AbstractCommand
{

    public function execute($mixed)
    {
        $payload = $this->payload($mixed);
        $this->checkMessageFlow($payload->getState(), 'delete');

        $this->queue->getRedis()->transaction(function ($tx) use ($payload) {
            /** @var $tx \Predis\Client */
            $tx->zrem($this->getKey($payload->getQueue(), Queue::STATE_RESERVED), $payload->getToken());
            $tx->zrem($this->getKey($payload->getQueue(), Queue::STATE_BURIED), $payload->getToken());
            $tx->zrem($this->getKey($payload->getQueue(), Queue::STATE_DELAYED), $payload->getToken()); // ?
            $tx->hdel($this->ns(Queue::NS_MESSAGE), $payload->getToken());
            $tx->hdel($this->ns(Queue::NS_MESSAGE_TO_QUEUE), $payload->getToken());
            $tx->hdel($this->ns(Queue::NS_MESSAGE_TO_STATE), $payload->getToken());
        });

        return $this;
    }

}
