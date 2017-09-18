<?php

namespace Simplario\Quedis;

use Simplario\Quedis\Interfaces\IteratorInterface;

/**
 * Class Iterator
 * @package Simplario\Quedis
 */
class Iterator implements IteratorInterface
{

    /**
     * @var \Simplario\Quedis\Queue
     */
    protected $queue;

    /**
     * @var array
     */
    protected $options;

    /**
     * Iterator constructor.
     * @param Queue $queue
     * @param array $options
     */
    public function __construct(Queue $queue, array $options = [])
    {
        $this->queue = $queue;
        $this->options = $options;
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        $options = $this->options;
        $index = 0;
        $loop = 0;
        while (true) {

            if ($options['strategy'] == 'pop') {
                $message = $this->queue->pop($options['queue'], $options['timeout']);
            } else {
                $message = $this->queue->reserve($options['queue'], $options['timeout']);
            }

            if ($message instanceof Message) {
                $index++;
                $callback($message, $this->queue);
            } else {
                break;
            }

            if (isset($options['sleep']) && $options['sleep'] > 0) {
                sleep($options['sleep']);
            }

            if (isset($options['limit-message']) && $options['limit-message'] == $index) {
                break;
            }

            $loop++;
            if (isset($options['limit-loop']) && $options['limit-loop'] == $loop) {
                break;
            }

        }

        return $this;
    }

}



