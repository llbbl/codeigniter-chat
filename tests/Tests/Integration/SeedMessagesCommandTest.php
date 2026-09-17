<?php

namespace Tests\Integration;

use App\Commands\SeedMessages;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

#[Group('integration')]
final class SeedMessagesCommandTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        $this->setBenchmarkMode(null);

        parent::tearDown();
    }

    public function testItRequiresBenchmarkMode(): void
    {
        $this->setBenchmarkMode(null);
        $command = new SeedMessages(service('logger'), service('commands'));

        $this->assertSame(1, $command->run(['1']));
        $this->assertSame(0, $this->db->table('users')->like('username', 'k6_user_', 'after')->countAllResults());
    }

    public function testItRejectsInvalidCounts(): void
    {
        $this->assertNull(SeedMessages::parseCount(null));
        $this->assertNull(SeedMessages::parseCount('0'));
        $this->assertNull(SeedMessages::parseCount('-1'));
        $this->assertNull(SeedMessages::parseCount('1000001'));
        $this->assertSame(25, SeedMessages::parseCount('25'));
    }

    public function testItReplacesBenchmarkMessagesAndCreatesLoadUsers(): void
    {
        $this->setBenchmarkMode('true');
        $command = new SeedMessages(service('logger'), service('commands'));

        $this->assertSame(0, $command->run(['3']));
        $this->assertSame(3, $this->benchmarkMessages()->countAllResults());
        $this->assertSame(100, $this->db->table('users')->like('username', 'k6_user_', 'after')->countAllResults());

        $this->assertSame(0, $command->run(['2']));
        $messages = $this->benchmarkMessages()->orderBy('messages.time')->get()->getResultArray();

        $this->assertCount(2, $messages);
        $this->assertSame('Benchmark message 00000001', $messages[0]['msg']);
        $this->assertSame('1700000001', (string) $messages[0]['time']);
    }

    private function setBenchmarkMode(?string $value): void
    {
        if ($value === null) {
            putenv('BENCHMARK_MODE');
            unset($_ENV['BENCHMARK_MODE'], $_SERVER['BENCHMARK_MODE']);

            return;
        }

        putenv('BENCHMARK_MODE=' . $value);
        $_ENV['BENCHMARK_MODE'] = $value;
        $_SERVER['BENCHMARK_MODE'] = $value;
    }

    private function benchmarkMessages(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table('messages AS messages')
            ->join('users AS users', 'users.id = messages.user_id')
            ->like('users.username', 'k6_user_', 'after');
    }
}
