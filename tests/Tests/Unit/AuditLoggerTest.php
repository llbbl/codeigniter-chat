<?php

namespace Tests\Unit;

use App\Contracts\AuditLogStore;
use App\Services\AuditLogger;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

/** @internal */
final class AuditLoggerTest extends CIUnitTestCase
{
    public function testRecordsAnonymousFailureWithRequestMetadata(): void
    {
        $store = $this->createMock(AuditLogStore::class);
        $store->expects($this->once())->method('saveAudit')->with($this->callback(static fn (array $row): bool => $row['event_type'] === 'auth.login.failure' && $row['user_id'] === null && $row['username_attempted'] === 'alice' && $row['ip_address'] !== '' && $row['user_agent'] === 'AuditLoggerTest' && str_contains($row['context'], 'invalid_credentials')));
        $request = new IncomingRequest(new App(), new URI('https://example.test/login'), null, new UserAgent());
        $request->setHeader('User-Agent', 'AuditLoggerTest');
        (new AuditLogger($store, $request))->record('auth.login.failure', null, ['username_attempted' => 'alice', 'reason' => 'invalid_credentials']);
    }
}
