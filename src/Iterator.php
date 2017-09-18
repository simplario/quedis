<?php

namespace Simplario\Quedis;

use Simplario\Quedis\Interfaces\IteratorInterface;

/**
 * Class Iterator
 * @package Simplario\Quedis
 */
class Iterator implements IteratorInterface
{

    const OPT_QUEUE = 'queue';       // queue name
    const OPT_TIMEOUT = 'timeout';   // 0 .. 999999999 sec
    const OPT_STRATEGY = 'strategy'; // 'pop' or 'reserve'
    const OPT_MESSAGES = 'messages'; // 0 .. 999999999 items
    const OPT_LOOPS = 'loops';       // 0 .. 999999999 sec
    const OPT_SLEEP = 'sleep';       // 0 .. 999999999 sec

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

        $options[self::OPT_TIMEOUT] = (int) isset($options[self::OPT_MESSAGES]) ? $options[self::OPT_MESSAGES] : 0;
        $options[self::OPT_SLEEP] = (int) isset($options[self::OPT_SLEEP]) ? $options[self::OPT_SLEEP] : 0;

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

            if (isset($options[self::OPT_STRATEGY]) &&  $options[self::OPT_STRATEGY] == 'pop') {
                $message = $this->queue->pop($options[self::OPT_QUEUE], $options[self::OPT_TIMEOUT]);
            } else {
                $message = $this->queue->reserve($options[self::OPT_QUEUE], $options[self::OPT_TIMEOUT]);
            }

            if ($message instanceof Message) {
                $index++;
                $callback($message, $this->queue);
            } else {
                break;
            }

            if (isset($options[self::OPT_MESSAGES]) && $options[self::OPT_MESSAGES] <= $index) {
                break;
            }

            $loop++;
            if (isset($options[self::OPT_LOOPS]) && $options[self::OPT_LOOPS] <= $loop) {
                break;
            }

            if ($options[self::OPT_SLEEP] > 0) {
                sleep($options[self::OPT_SLEEP]);
            }
        }

        return $this;
    }

}



