<?php

namespace Simplario\Quedis\Command;

use Simplario\Quedis\Queue;

/**
 * Class PutCommand
 *
 * @package Simplario\Quedis
 */
class ReleaseCommand extends AbstractCommand
{

    public function execute($mixed, $delay = 0)
    {
        $payload = $this->payload($mixed);
        $this->checkMessageFlow($payload->getState(), 'release');
        $delay = $this->parseDelay($delay);

        $result = $this->queue->getRedis()->transaction(function ($tx) use ($payload, $delay) {
            /** @var $tx \Predis\Client */
            $tx->zrem($this->getKey($payload->getQueue(), Queue::STATE_RESERVED), $payload->getToken());
            $tx->hset($this->ns(Queue::NS_MESSAGE_TO_STATE), $payload->getToken(), Queue::STATE_READY);

            if ($delay == 0) {
                $tx->rpush($this->getKey($payload->getQueue(), Queue::STATE_READY), $payload->getToken());
            } else {
                $tx->zadd($this->getKey($payload->getQueue(), Queue::STATE_DELAYED), time() + $delay,
                    $payload->getToken());
            }
        });

        $this->checkTransactionResult($result, 'release');

        return $this;
    }

}
