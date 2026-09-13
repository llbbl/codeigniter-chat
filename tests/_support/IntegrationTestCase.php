<?php

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

abstract class IntegrationTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use UsesApplication;

    /** @var string */
    protected $DBGroup = 'tests';

    /** @var string */
    protected $namespace = 'App';

    /** @var bool */
    protected $refresh = true;

    /** @var string */
    protected $basePath = APPPATH . 'Database';

    private string|false $tokenFileContents = false;

    protected function setUp(): void
    {
        parent::setUp();

        $tokenFile = WRITEPATH . 'websocket_tokens.json';
        $this->tokenFileContents = is_file($tokenFile) ? file_get_contents($tokenFile) : false;

        $this->resetApplicationServices();
    }

    protected function tearDown(): void
    {
        $this->restoreWebSocketTokens();
        $this->resetApplicationServices();

        parent::tearDown();
    }

    /** @param array<string, mixed> $session */
    protected function loginAs(array $session): static
    {
        $this->withSession([
            'logged_in' => true,
            'user_id' => $session['id'],
            'username' => $session['username'],
            'email' => $session['email'],
        ]);

        return $this;
    }

    protected function assertMessageInDatabase(string $username, string $message): void
    {
        $this->seeInDatabase('messages', [
            'user' => $username,
            'msg' => $message,
        ]);
    }

    private function resetApplicationServices(): void
    {
        foreach (['userRepository', 'chatRepository', 'chatFormatter', 'auditLogger', 'response', 'security'] as $service) {
            Services::resetSingle($service);
        }
    }

    private function restoreWebSocketTokens(): void
    {
        $tokenFile = WRITEPATH . 'websocket_tokens.json';

        if ($this->tokenFileContents === false) {
            if (is_file($tokenFile)) {
                unlink($tokenFile);
            }

            return;
        }

        file_put_contents($tokenFile, $this->tokenFileContents, LOCK_EX);
    }
}
