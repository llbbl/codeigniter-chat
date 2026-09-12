<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\SecurityHeaders;

class SecurityHeadersFilter implements FilterInterface
{
    private SecurityHeaders $config;

    private string $environment;

    public function __construct(?SecurityHeaders $config = null, ?string $environment = null)
    {
        $this->config = $config ?? new SecurityHeaders();
        $this->environment = $environment ?? ENVIRONMENT;
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        // This filter only augments the completed response.
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        $headers = $this->config->headers[$this->environment] ?? $this->config->headers['development'];
        $override = is_array($arguments) ? ($arguments[0] ?? null) : null;

        if (is_string($override) && isset($this->config->overrides[$override])) {
            $headers = array_replace($headers, $this->config->overrides[$override]);
        }

        foreach ($headers as $name => $value) {
            if (! $response->hasHeader($name)) {
                $response->setHeader($name, $value);
            }
        }

        if (! $response->hasHeader('Report-To') && $this->config->reportTo !== null) {
            $reportTo = json_encode($this->config->reportTo, JSON_UNESCAPED_SLASHES);

            if (is_string($reportTo)) {
                $response->setHeader('Report-To', $reportTo);
            }
        }
    }
}
