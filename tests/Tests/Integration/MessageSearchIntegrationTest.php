<?php

namespace Tests\Integration;

use App\Models\ChatModel;
use App\Models\UserModel;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

#[Group('integration')]
#[Group('message-search')]
final class MessageSearchIntegrationTest extends IntegrationTestCase
{
    public function testSearchIndexExistsAndStaysSynchronized(): void
    {
        $model = new ChatModel();
        $id = $model->insertMsg('alice', 'A cobalt telescope is ready', 1_700_000_000);
        $this->assertIsInt($id);

        $result = $model->searchMessages('cobalt telescope');
        $this->assertSame($id, (int) $result['messages'][0]['id']);

        $this->db->table('messages')->where('id', $id)->update(['msg' => 'A vermilion telescope is ready']);
        $this->assertSame([], $model->searchMessages('cobalt')['messages']);
        $this->assertSame($id, (int) $model->searchMessages('vermilion')['messages'][0]['id']);

        $this->db->table('messages')->where('id', $id)->delete();
        $this->assertSame([], $model->searchMessages('vermilion')['messages']);

        $this->assertSearchIndexExists();
    }

    public function testSearchCombinesTextUserTimeAndPagination(): void
    {
        $this->db->table('messages')->insertBatch([
            ['user' => 'alice', 'msg' => 'Indexed meteor report one', 'time' => 100],
            ['user' => 'alice', 'msg' => 'Indexed meteor report two', 'time' => 200],
            ['user' => 'alice', 'msg' => 'Indexed meteor report outside range', 'time' => 300],
            ['user' => 'bob', 'msg' => 'Indexed meteor report by Bob', 'time' => 150],
            ['user' => 'alice', 'msg' => 'Unrelated conversation', 'time' => 175],
        ]);

        $result = (new ChatModel())->searchMessages('Indexed meteor report', 'alice', 100, 250, 2, 1);

        $this->assertSame('Indexed meteor report one', $result['messages'][0]['msg']);
        $this->assertSame([
            'page' => 2,
            'perPage' => 1,
            'totalItems' => 2,
            'totalPages' => 2,
            'hasNext' => false,
            'hasPrev' => true,
        ], $result['pagination']);
    }

    public function testAuthenticatedHttpSearchUsesTheRealRepository(): void
    {
        $userModel = new UserModel();
        $userId = $userModel->createUser('searcher', 'searcher@example.com', 'Password123!');
        $this->assertIsInt($userId);
        $user = $userModel->find($userId);
        $this->assertIsArray($user);

        $this->db->table('messages')->insertBatch([
            ['user' => 'alice', 'msg' => 'The classroom has a brass telescope', 'time' => 200],
            ['user' => 'alice', 'msg' => 'The classroom has a paper map', 'time' => 100],
            ['user' => 'bob', 'msg' => 'The classroom has a brass telescope', 'time' => 200],
        ]);

        $response = $this->loginAs($user)
            ->withHeaders(['Origin' => 'http://localhost'])
            ->call('get', '/api/v1/messages/search?text=brass%20telescope&user=alice&from=150&to=250');

        $response->assertOK();
        $payload = json_decode($response->getJSON(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1, $payload['messages']);
        $this->assertSame('alice', $payload['messages'][0]['user']);
        $this->assertSame('The classroom has a brass telescope', $payload['messages'][0]['msg']);
        $this->assertSame(1, $payload['pagination']['totalItems']);
        $this->assertSame('brass telescope', $payload['filters']['text']);
    }

    public function testFullTextSearchUsesTheNativeIndexAtPracticalScale(): void
    {
        $rows = [];
        for ($i = 0; $i < 2_000; ++$i) {
            $rows[] = [
                'user' => 'user' . ($i % 20),
                'msg' => $i === 1_337 ? 'distinctive quasar observatory signal' : "ordinary classroom message {$i}",
                'time' => 1_700_000_000 + $i,
            ];
        }
        foreach (array_chunk($rows, 250) as $chunk) {
            $this->db->table('messages')->insertBatch($chunk);
        }

        $model = new ChatModel();
        $model->searchMessages('distinctive quasar observatory signal', page: 1, perPage: 10);

        $started = hrtime(true);
        $result = $model->searchMessages('distinctive quasar observatory signal', page: 1, perPage: 10);
        $elapsedMilliseconds = (hrtime(true) - $started) / 1_000_000;

        $this->assertCount(1, $result['messages']);
        $this->assertSame('user17', $result['messages'][0]['user']);
        // Shared CI runners can add substantial scheduling and database-service
        // latency. The query-plan assertion below remains the deterministic
        // guard that the native index is used; this ceiling catches only clear
        // practical regressions without making normal runner variance fatal.
        $this->assertLessThan(500, $elapsedMilliseconds, 'A warmed indexed search of 2,000 messages should finish within 500 milliseconds.');
        $this->assertQueryPlanUsesSearchIndex('distinctive quasar observatory signal');
    }

    private function assertSearchIndexExists(): void
    {
        if ($this->db->getPlatform() === 'SQLite3') {
            $table = $this->db->prefixTable('messages_fts');
            $row = $this->db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table])->getRowArray();
            $this->assertStringContainsString('fts5', strtolower((string) ($row['sql'] ?? '')));

            return;
        }

        $table = $this->db->prefixTable('messages');
        $indexes = $this->db->query("SHOW INDEX FROM {$table} WHERE Key_name = 'idx_messages_fulltext'")->getResultArray();
        $this->assertCount(2, $indexes);
        $this->assertSame(['user', 'msg'], array_values(array_unique(array_column($indexes, 'Column_name'))));
    }

    private function assertQueryPlanUsesSearchIndex(string $text): void
    {
        $messages = $this->db->prefixTable('messages');

        if ($this->db->getPlatform() === 'SQLite3') {
            $search = $this->db->prefixTable('messages_fts');
            $plan = $this->db->query(
                "EXPLAIN QUERY PLAN SELECT messages.* FROM {$messages} AS messages INNER JOIN {$search} ON {$search}.rowid = messages.id WHERE {$search} MATCH ?",
                ['"' . $text . '"'],
            )->getResultArray();
            $details = strtolower(implode(' ', array_column($plan, 'detail')));
            $this->assertStringContainsString('virtual table index', $details);

            return;
        }

        $plan = $this->db->query(
            "EXPLAIN SELECT messages.* FROM {$messages} AS messages WHERE MATCH(messages.user, messages.msg) AGAINST (? IN NATURAL LANGUAGE MODE)",
            [$text],
        )->getResultArray();
        $this->assertContains('fulltext', array_map('strtolower', array_column($plan, 'type')));
        $this->assertContains('idx_messages_fulltext', array_column($plan, 'key'));
    }
}
