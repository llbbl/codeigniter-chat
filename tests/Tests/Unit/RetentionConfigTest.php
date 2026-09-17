<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Retention;
use InvalidArgumentException;

final class RetentionConfigTest extends CIUnitTestCase
{
    public function testDefaultPoliciesCoverArchiveAndAuxiliaryData(): void
    {
        $policies = (new Retention())->selectedPolicies();

        $this->assertSame(['messages', 'archived_messages', 'csp_reports', 'audit_log'], array_keys($policies));
        $this->assertSame('archive', $policies['messages']['action']);
        $this->assertSame('delete', $policies['archived_messages']['action']);
    }

    public function testOnePolicyCanBeSelected(): void
    {
        $policies = (new Retention())->selectedPolicies('csp_reports');

        $this->assertSame(['csp_reports'], array_keys($policies));
        $this->assertSame(30, $policies['csp_reports']['max_age_days']);
    }

    public function testUnknownPolicyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown retention policy: missing');

        (new Retention())->selectedPolicies('missing');
    }

    public function testUnsafeIdentifiersAndUnsupportedActionsAreRejected(): void
    {
        $config = new Retention();
        $config->policies = [
            'audit_log; DROP TABLE users' => [
                'column' => 'created_at',
                'max_age_days' => 1,
                'action' => 'delete',
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid retention table name');

        $config->selectedPolicies();
    }

    public function testOnlyMessagesCanUseArchiveAction(): void
    {
        $config = new Retention();
        $config->policies = [
            'audit_log' => [
                'column' => 'created_at',
                'max_age_days' => 1,
                'action' => 'archive',
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only the messages policy currently supports archiving.');

        $config->selectedPolicies();
    }
}
