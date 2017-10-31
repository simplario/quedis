<?php

// Bootstrap
// =========================================

require_once(__DIR__ . '/vendor/autoload.php');

// Init
// =========================================

$redis = new \Predis\Client();
$quedis = new \Simplario\Quedis\Queue($redis, 'ExampleNameSpace');


// Put message to Quedis
// =========================================


// add new messages
$message11 = $quedis->put('transaction-queue', 'transaction-11');
$message12 = $quedis->put('transaction-queue', new \Simplario\Quedis\Message('transaction-12'));


// with delay
$message22 = $quedis->put('transaction-queue', 'transaction-21', 60 * 5);
$message22 = $quedis->put('transaction-queue', 'transaction-22', (new \DateTime())->modify('+1 day'));


// with priority
$message32 = $quedis->put('transaction-queue', 'transaction-31', 0, 'high');
$message32 = $quedis->put('transaction-queue', 'transaction-32', 0, 'low');


// Get Quedis statistic
// =========================================


// for concrete queue
$queueStat = $quedis->stats('transaction-queue');


// for all queues
$statsAll = $quedis->stats();


// Stop/start queue
// =========================================


// stop queue
$quedis->stop('transaction-queue');


// for all queues
$quedis->start('transaction-queue');


// check
$isStop = $quedis->isStop('transaction-queue');
print_r($isStop);


/**
 *
 * Take message from Quedis
 *
 *   Message flows (like in the Beanstalk: http://beanstalkc.readthedocs.io/en/latest/tutorial.html )
 *   ------------------------------------------------------------------------------------------------
 *
 *   1)   put            pop
 *       -----> [READY] --------> *poof*
 *
 *
 *   2)   put            reserve               delete
 *       -----> [READY] ---------> [RESERVED] --------> *poof*
 *
 *
 *   3)   put with delay               release with delay
 *       ----------------> [DELAYED] <------------.
 *                             |                   |
 *                             | (time passes)     |
 *                             |                   |
 *        put                  v     reserve       |       delete
 *       -----------------> [READY] ---------> [RESERVED] --------> *poof*
 *                            ^  ^                |  |
 *                            |   \  release      |  |
 *                            |    ``-------------'   |
 *                            |                      |
 *                            | kick                 |
 *                            |                      |
 *                            |       bury           |
 *                         [BURIED] <---------------'
 *                            |
 *                            |  delete
 *                             ``--------> *poof*
 *
 */


// just pop single message
$message = $quedis->pop('transaction-queue');
print_r($message);


// just pop single message with timeout (redis blpop timeout)
$message = $quedis->pop('transaction-queue', 10);
print_r($message);


// reserve flow
$message = $quedis->reserve('transaction-queue', 10);
$quedis->delete($message);


// reserve > bury > kick > reserve > delete
$message = $quedis->reserve('transaction-queue', 10);
// something goes wrong ...
$quedis->bury($message);
// ok lets retry one more time ...
$quedis->kick($message);
$messageSame = $quedis->reserve($message);
// all is ok!
$quedis->delete($messageSame);


// Iterator usage
// =========================================

// iterate reserve all messages
$iterator = $quedis->iterator('transaction-queue', 'reserve', 10);
foreach($iterator as $index => $message){
    print_r($message);

    $quedis->delete($message);
}


// or like standalone with pop logic
$queue = new \Simplario\Quedis\Queue(new \Predis\Client(), 'super-puper-quedis');
$iterator = new \Simplario\Quedis\Iterator($queue, 'transaction-queue', 'pop', 10);
foreach($iterator as $index => $message){
    print_r($message);
}