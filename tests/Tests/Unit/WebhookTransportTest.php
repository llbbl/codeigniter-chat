<?php

namespace Tests\Unit;

use App\Services\CodeIgniterWebhookHttpClient;
use App\Services\PinnedWebhookCURLRequest;
use App\Services\WebhookUrlValidator;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Services;
use InvalidArgumentException;

/** @internal */
final class WebhookTransportTest extends CIUnitTestCase
{
    public function testTransportPinsDnsAndPreservesTheOriginalHostname(): void
    {
        $resolutions = 0;
        $validator = new WebhookUrlValidator(static function (string $host) use (&$resolutions): array {
            self::assertSame('hooks.example', $host);
            ++$resolutions;

            return $resolutions === 1 ? ['8.8.8.8', '2606:4700:4700::1111'] : ['127.0.0.1'];
        });
        $transport = $this->transport($validator);
        $client = new CodeIgniterWebhookHttpClient($transport);
        $this->assertSame(['status' => 204, 'body' => ''], $client->send('https://hooks.example:8443/events', '{}', []));

        $this->assertSame(1, $resolutions);
        $this->assertSame('https://hooks.example:8443/events', $transport->sentOptions[CURLOPT_URL]);
        $this->assertSame(['hooks.example:8443:8.8.8.8,[2606:4700:4700::1111]'], $transport->sentOptions[CURLOPT_RESOLVE]);

        // A later delivery must revalidate; the now-private DNS result never reaches cURL.
        try {
            $client->send('https://hooks.example:8443/events', '{}', []);
            $this->fail('The changed DNS result must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame(2, $resolutions);
            $this->assertSame(1, $transport->connections);
        }
    }

    public function testPrivateAndMixedDnsAnswersNeverReachTheTransport(): void
    {
        foreach ([[], ['10.0.0.1'], ['8.8.8.8', '127.0.0.1'], ['8.8.8.8', '::1'], ['::ffff:127.0.0.1']] as $addresses) {
            $transport = $this->transport(new WebhookUrlValidator(static fn (string $host): array => $addresses));
            try {
                (new CodeIgniterWebhookHttpClient($transport))->send('https://hooks.example/events', '{}', []);
                $this->fail('Every DNS address must be public.');
            } catch (InvalidArgumentException) {
                $this->assertSame(0, $transport->connections);
            }
        }
    }

    public function testTransportForcesTlsValidationAndDisablesRedirectsProxiesAndConnectionReuse(): void
    {
        $transport = $this->transport(new WebhookUrlValidator(static fn (string $host): array => ['8.8.8.8']));
        $transport->post('https://hooks.example/events', [
            'allow_redirects' => true,
            'proxy' => 'http://127.0.0.1:8080',
            'verify' => false,
            'fresh_connect' => false,
        ]);

        $this->assertTrue($transport->sentOptions[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $transport->sentOptions[CURLOPT_SSL_VERIFYHOST]);
        $this->assertFalse($transport->sentOptions[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame('', $transport->sentOptions[CURLOPT_PROXY]);
        $this->assertSame('*', $transport->sentOptions[CURLOPT_NOPROXY]);
        $this->assertTrue($transport->sentOptions[CURLOPT_FRESH_CONNECT]);
        $this->assertTrue($transport->sentOptions[CURLOPT_FORBID_REUSE]);
        $this->assertArrayNotHasKey(CURLOPT_SHARE, $transport->sentOptions);
        $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $transport->sentOptions[CURLOPT_PROTOCOLS]);
        $this->assertSame(['hooks.example:443:8.8.8.8'], $transport->sentOptions[CURLOPT_RESOLVE]);
    }

    public function testDevelopmentLocalhostExceptionUsesAPinnedLoopbackAddressWithoutDns(): void
    {
        $validator = new WebhookUrlValidator(static function (string $host): array {
            self::fail('The loopback exception must never use DNS.');
        }, allowHttpLoopback: true);
        $transport = $this->transport($validator);
        (new CodeIgniterWebhookHttpClient($transport))->send('http://localhost:8080/receive', '{}', []);

        $this->assertSame(['localhost:8080:127.0.0.1'], $transport->sentOptions[CURLOPT_RESOLVE]);
        $this->assertSame(['127.0.0.1'], $validator->resolveAllowedAddresses('http://127.0.0.1:8080/receive'));
        $this->assertSame(['::1'], $validator->resolveAllowedAddresses('http://[::1]:8080/receive'));
        $this->assertFalse((new WebhookUrlValidator())->isAllowed('http://localhost:8080/receive'));
        $this->assertFalse($validator->isAllowed('https://localhost/receive'));
        $this->assertFalse($validator->isAllowed('http://hooks.example/receive'));
    }

    public function testPublicIpLiteralsDoNotRequireDns(): void
    {
        $transport = $this->transport(new WebhookUrlValidator(static function (string $host): array {
            self::fail('IP literals must never use DNS.');
        }));
        foreach (['https://8.8.8.8/events', 'https://[2606:4700:4700::1111]:8443/events'] as $url) {
            (new CodeIgniterWebhookHttpClient($transport))->send($url, '{}', []);
            $this->assertSame([], $transport->sentOptions[CURLOPT_RESOLVE]);
            $this->assertSame($url, $transport->sentOptions[CURLOPT_URL]);
        }
    }

    public function testSchemeAndHostnameCaseKeepThePinOnTheActualUrlPort(): void
    {
        $transport = $this->transport(new WebhookUrlValidator(static fn (string $host): array => ['8.8.8.8']));
        foreach ([
            ['HTTPS://Hooks.Example/events', 'hooks.example:443:8.8.8.8'],
            ['hTtPs://Hooks.Example./events', 'hooks.example.:443:8.8.8.8'],
            ['HTTPS://Hooks.Example.:9443/events', 'hooks.example.:9443:8.8.8.8'],
        ] as [$url, $resolve]) {
            (new CodeIgniterWebhookHttpClient($transport))->send($url, '{}', []);
            $this->assertSame($url, $transport->sentOptions[CURLOPT_URL]);
            $this->assertSame([$resolve], $transport->sentOptions[CURLOPT_RESOLVE]);
        }
    }

    public function testFactoryUsesTheRuntimeEnvironmentWhenRequestGlobalsAreReset(): void
    {
        $this->assertSame('testing', ENVIRONMENT);
        $serverEnvironment = $_SERVER['CI_ENVIRONMENT'] ?? null;
        $processEnvironment = getenv('CI_ENVIRONMENT');
        unset($_SERVER['CI_ENVIRONMENT']);
        putenv('CI_ENVIRONMENT');

        try {
            $this->assertTrue(Services::webhookUrlValidator(false)->isAllowed('http://localhost:8080/receive'));
            $this->assertFalse((new WebhookUrlValidator())->isAllowed('http://localhost:8080/receive'));
            $this->assertFalse((new WebhookUrlValidator())->isAllowed('http://127.0.0.1:8080/receive'));
        } finally {
            if ($serverEnvironment !== null) {
                $_SERVER['CI_ENVIRONMENT'] = $serverEnvironment;
            }
            putenv($processEnvironment === false ? 'CI_ENVIRONMENT' : 'CI_ENVIRONMENT=' . $processEnvironment);
        }
    }

    private function transport(WebhookUrlValidator $validator): PinnedWebhookCURLRequest
    {
        return new class(config(App::class), $validator) extends PinnedWebhookCURLRequest {
            public array $sentOptions = [];
            public int $connections = 0;

            protected function sendRequest(array $curlOptions = []): string
            {
                ++$this->connections;
                $this->sentOptions = $curlOptions;

                return "HTTP/1.1 204 No Content\r\n\r\n";
            }
        };
    }
}
