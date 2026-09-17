<?php

namespace Tests\Integration;

use App\Commands\RetentionApply;
use App\Models\ChannelModel;
use App\Models\ChatModel;
use App\Models\UserModel;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

#[Group('integration')]
final class RetentionIntegrationTest extends IntegrationTestCase
{
    public function testDryRunReportsWithoutDeleting(): void
    {
        $this->insertCspReport('old', date('Y-m-d H:i:s', strtotime('-31 days')));
        $this->insertCspReport('new', date('Y-m-d H:i:s', strtotime('-29 days')));

        $command = new RetentionApply(service('logger'), service('commands'));
        $this->assertSame(0, $command->run([
            'policy' => 'csp_reports',
            'dry-run' => null,
            'batch-size' => '1',
        ]));

        $this->seeInDatabase('csp_reports', ['violated_directive' => 'old']);
        $this->seeInDatabase('csp_reports', ['violated_directive' => 'new']);
    }

    public function testDeletePolicyUsesBoundedBatchesAndIsIdempotent(): void
    {
        $old = date('Y-m-d H:i:s', strtotime('-31 days'));
        foreach (['old-one', 'old-two', 'old-three'] as $directive) {
            $this->insertCspReport($directive, $old);
        }
        $this->insertCspReport('new', date('Y-m-d H:i:s', strtotime('-29 days')));

        $command = new RetentionApply(service('logger'), service('commands'));
        $params = ['policy' => 'csp_reports', 'batch-size' => '2'];
        $this->assertSame(0, $command->run($params));
        $this->assertSame(0, $this->db->table('csp_reports')->like('violated_directive', 'old-', 'after')->countAllResults());
        $this->seeInDatabase('csp_reports', ['violated_directive' => 'new']);

        $this->assertSame(0, $command->run($params));
        $this->assertSame(1, $this->db->table('csp_reports')->countAllResults());
    }

    public function testArchivePolicyDelegatesToMessageArchivingAndIsIdempotent(): void
    {
        $user = new UserModel();
        $userId = $user->createUser('retention-user', 'retention@example.com', 'Password123!');
        $this->assertIsInt($userId);
        $channelId = (new ChannelModel())->createPublic('Retention', 'retention', null, $userId);
        $this->assertIsInt($channelId);

        $messages = new ChatModel();
        $messages->insertMsg('retention-user', 'old message', strtotime('-91 days'), $channelId);
        $messages->insertMsg('retention-user', 'new message', strtotime('-89 days'), $channelId);

        $command = new RetentionApply(service('logger'), service('commands'));
        $params = ['policy' => 'messages', 'batch-size' => '1'];
        $this->assertSame(0, $command->run($params));
        $this->seeInDatabase('archived_messages', ['channel_id' => $channelId, 'msg' => 'old message']);
        $this->seeInDatabase('messages', ['channel_id' => $channelId, 'msg' => 'new message']);

        $this->assertSame(0, $command->run($params));
        $this->assertSame(1, $this->db->table('archived_messages')->where('channel_id', $channelId)->countAllResults());
    }

    public function testInvalidOptionsReturnFailureWithoutMutation(): void
    {
        $command = new RetentionApply(service('logger'), service('commands'));

        $this->assertSame(1, $command->run(['policy' => 'missing']));
        $this->assertSame(1, $command->run(['batch-size' => '0']));
        $this->assertNull(RetentionApply::parseBatchSize('0'));
        $this->assertNull(RetentionApply::parseBatchSize('10001'));
        $this->assertSame(5_000, RetentionApply::parseBatchSize('5000'));
    }

    private function insertCspReport(string $directive, string $reportedAt): void
    {
        $this->db->table('csp_reports')->insert([
            'document_uri' => 'https://example.com/chat',
            'violated_directive' => $directive,
            'blocked_uri' => null,
            'source_file' => null,
            'line_number' => null,
            'user_agent' => 'PHPUnit',
            'reported_at' => $reportedAt,
        ]);
    }
}
