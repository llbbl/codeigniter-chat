<?php

namespace App\Services;

use Closure;

final class WebhookUrlValidator
{
    /** @var Closure(string): list<string> */
    private readonly Closure $resolver;

    /** @param Closure(string): list<string>|null $resolver */
    public function __construct(?Closure $resolver = null, private readonly bool $allowHttpLoopback = false)
    {
        $this->resolver = $resolver ?? self::resolveHost(...);
    }

    public function isAllowed(string $url): bool
    {
        if ($url === '' || strlen($url) > 2_048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $loopback = in_array($host, ['127.0.0.1', '::1', 'localhost'], true);
        if ($scheme === 'http') {
            return $this->allowHttpLoopback && $loopback;
        }
        if ($scheme !== 'https' || $host === '' || $loopback || str_ends_with($host, '.localhost')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicAddress($host);
        }

        $addresses = ($this->resolver)($host);
        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private static function resolveHost(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            return [];
        }

        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    private static function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
