<?php

namespace Simplario\Quedis;

use Simplario\Quedis\Interfaces\IteratorInterface;
use Simplario\Quedis\Interfaces\MessageInterface;
use Simplario\Quedis\Interfaces\QueueInterface;

/**
 * Class Iterator
 *
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
    const OPT_FINISH = 'finish';     // flag for finish iteration

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
     *
     * @param QueueInterface $queue
     * @param array          $options
     */
    public function __construct(QueueInterface $queue, array $options = [])
    {
        $options[self::OPT_TIMEOUT] = isset($options[self::OPT_TIMEOUT]) ? (int) $options[self::OPT_TIMEOUT] : 0;
        $options[self::OPT_SLEEP] = isset($options[self::OPT_SLEEP]) ? (int) $options[self::OPT_SLEEP] : 0;
        $options[self::OPT_STRATEGY] = isset($options[self::OPT_STRATEGY]) ? $options[self::OPT_STRATEGY] : 'pop';
        $options[self::OPT_FINISH] = false;

        $this->queue = $queue;
        $this->options = $options;
    }

    /**
     * @param callable $callback
     *
     * @return $this
     */
    public function each(callable $callback)
    {
        $options = $this->options;
        $method = in_array($options[self::OPT_STRATEGY], ['pop', 'reserve']) ? $options[self::OPT_STRATEGY] : 'pop';

        $queueName = $options[self::OPT_QUEUE];
        $timeout = $options[self::OPT_TIMEOUT];

        $count = 0;
        $loop = 0;

        while ($message = $this->queue->{$method}($queueName, $timeout)) {

            if ($message instanceof MessageInterface) {
                $callback($message, $this->queue, $this);
                $count++;
            }

            if ($this->isFinish()) {
                break;
            }

            if ($this->isMessagesExceed($count) || $this->isLoopsExceed($loop++)) {
                break;
            }

            $this->trySleep();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function finish()
    {
        $this->options[self::OPT_FINISH] = true;

        return $this;
    }

    /**
     * @return bool
     */
    protected function isFinish()
    {
        return $this->options[self::OPT_FINISH];
    }

    /**
     * @param $count
     *
     * @return bool
     */
    protected function isMessagesExceed($count)
    {
        return isset($this->options[self::OPT_MESSAGES]) && $this->options[self::OPT_MESSAGES] <= $count;
    }

    /**
     * @param $loop
     *
     * @return bool
     */
    protected function isLoopsExceed($loop)
    {
        return isset($this->options[self::OPT_LOOPS]) && $this->options[self::OPT_LOOPS] <= $loop;
    }

    /**
     * @return $this
     */
    protected function trySleep()
    {
        if ($this->options[self::OPT_SLEEP] > 0) {
            sleep($this->options[self::OPT_SLEEP]);
        }

        return $this;
    }
}