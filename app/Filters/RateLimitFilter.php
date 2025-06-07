<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;

class RateLimitFilter implements FilterInterface
{
    /**
     * Maximum number of requests allowed within the time window
     *
     * @var int
     */
    protected $maxRequests = 10;

    /**
     * Time window in seconds
     *
     * @var int
     */
    protected $timeWindow = 60;

    /**
     * Check if the request exceeds the rate limit
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Get the user's identifier (IP address for guests, user_id for logged in users)
        $identifier = session()->get('user_id') ?? $request->getIPAddress();

        // Get the current timestamp
        $now = Time::now()->getTimestamp();

        // Initialize or get the user's request history from the session
        $history = session()->get('rate_limit_' . $identifier) ?? [];

        // Remove requests that are outside the time window
        $history = array_filter($history, function($timestamp) use ($now) {
            return $timestamp > ($now - $this->timeWindow);
        });

        // Check if the user has exceeded the rate limit
        if (count($history) >= $this->maxRequests) {
            // Return 429 Too Many Requests
            return service('response')
                ->setStatusCode(429)
                ->setBody('Too many requests. Please try again later.');
        }

        // Add the current request to the history
        $history[] = $now;

        // Save the updated history to the session
        session()->set('rate_limit_' . $identifier, $history);
    }

    /**
     * We don't have anything to do after the controller.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}