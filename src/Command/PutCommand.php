<?php

namespace Simplario\Quedis\Command;

use Simplario\Quedis\Queue;

/**
 * Class PutCommand
 *
 * @package Simplario\Quedis
 */
class PutCommand extends AbstractCommand
{

    public function execute($queue, $data, $delay, $priority)
    {
        $message = $this->createMessage($data);
        $delay = $this->parseDelay($delay);

        $result = $this->queue->getRedis()->transaction(function ($tx) use ($queue, $message, $delay, $priority) {
            /** @var $tx \Predis\Client */

            $state = $delay == 0 ? Queue::STATE_READY : Queue::STATE_DELAYED;

            $tx->hset($this->ns(Queue::NS_MESSAGE), $message->getToken(), $message->encode());
            $tx->hset($this->ns(Queue::NS_MESSAGE_TO_QUEUE), $message->getToken(), $queue);
            $tx->hset($this->ns(Queue::NS_MESSAGE_TO_STATE), $message->getToken(), $state);

            if ($state === Queue::STATE_READY) {
                if (Queue::PRIORITY_HIGH === $priority) {
                    $tx->lpush($this->getKey($queue, Queue::STATE_READY), $message->getToken());
                } else {
                    $tx->rpush($this->getKey($queue, Queue::STATE_READY), $message->getToken());
                }
            } else {
                $tx->zadd($this->getKey($queue, Queue::STATE_DELAYED), time() + $delay, $message->getToken());
            }
        });

        $this->checkTransactionResult($result, 'put');

        return $message;
    }

}
