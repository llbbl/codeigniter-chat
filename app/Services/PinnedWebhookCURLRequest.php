<?php

namespace App\Services;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\URI;
use Config\App;
use InvalidArgumentException;

/** Keeps the validated DNS result attached to the connection cURL actually opens. */
class PinnedWebhookCURLRequest extends CURLRequest
{
    public function __construct(App $config, private readonly WebhookUrlValidator $urlValidator)
    {
        parent::__construct($config, new URI());
    }

    protected function setCURLOptions(array $curlOptions = [], array $config = [])
    {
        $curlOptions = parent::setCURLOptions($curlOptions, $config);
        $url = (string) ($curlOptions[CURLOPT_URL] ?? '');
        $addresses = $this->urlValidator->resolveAllowedAddresses($url);
        if ($addresses === []) {
            throw new InvalidArgumentException('Webhook endpoint does not resolve to an allowed address.');
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $port = parse_url($url, PHP_URL_PORT) ?? ($scheme === 'https' ? 443 : 80);
        $curlOptions[CURLOPT_RESOLVE] = [];
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) === false) {
            $resolved = array_map(static fn (string $address): string => str_contains($address, ':') ? '[' . $address . ']' : $address, $addresses);
            $curlOptions[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . implode(',', $resolved)];
        }

        // Keep the URL hostname for Host/SNI/certificate validation. Proxies and
        // pooled connections must not bypass this request's approved addresses.
        unset($curlOptions[CURLOPT_SHARE]);
        $curlOptions[CURLOPT_PROXY] = '';
        $curlOptions[CURLOPT_NOPROXY] = '*';
        $curlOptions[CURLOPT_FRESH_CONNECT] = true;
        $curlOptions[CURLOPT_FORBID_REUSE] = true;
        $curlOptions[CURLOPT_SSL_VERIFYPEER] = true;
        $curlOptions[CURLOPT_SSL_VERIFYHOST] = 2;
        $curlOptions[CURLOPT_FOLLOWLOCATION] = false;
        $curlOptions[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;

        return $curlOptions;
    }
}
