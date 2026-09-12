<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configures response-security headers by environment.
 */
class SecurityHeaders extends BaseConfig
{
    /**
     * @var array<string, array<string, string>>
     */
    public array $headers = [
        'production' => [
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-site',
        ],
        'development' => [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-site',
        ],
        'testing' => [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-site',
        ],
    ];

    /**
     * Named, opt-in adjustments for routes with a documented exception.
     *
     * @var array<string, array<string, string>>
     */
    public array $overrides = [
        'frame-relaxed' => [
            'X-Frame-Options' => 'SAMEORIGIN',
        ],
    ];

    /**
     * Reporting API configuration. Set an absolute endpoint URL when CSP
     * reports should be delivered through the modern reporting mechanism.
     *
     * @var array{group: string, max_age: int, endpoints: list<array{url: string}>}|null
     */
    public ?array $reportTo = [
        'group' => 'csp-endpoint',
        'max_age' => 86400,
        'endpoints' => [['url' => '/csp-report']],
    ];
}
