<?php

namespace Acquia\Locker\Guzzle\Plugin\Backoff;

use Guzzle\Plugin\Backoff\AbstractBackoffStrategy;
use Guzzle\Plugin\Backoff\BackoffStrategyInterface;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Exception\HttpException;

/**
 * Strategy that will not retry more than a certain number of times.
 */
class TimeoutBackoffStrategy extends AbstractBackoffStrategy
{
    /** @var int Maximum time allowed */
    protected $timeout;

    protected $start_time;

    /**
     * @param int                      $maxRetries Maximum number of retries per request
     * @param BackoffStrategyInterface $next The optional next strategy
     */
    public function __construct($timeout, BackoffStrategyInterface $next = null)
    {
        $this->timeout = $timeout;
        $this->next = $next;
    }

    public function makesDecision()
    {
        return true;
    }

    protected function getDelay($retries, RequestInterface $request, Response $response = null, HttpException $e = null)
    {
        $time = time();
        if (!isset($this->start_time))
        {
            $this->end_time = $time + $this->timeout;
        }
        return $time <= $this->end_time ? null : false;
    }
}
