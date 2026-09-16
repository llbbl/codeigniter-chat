<?php

namespace Tests\Integration;

use App\Commands\ArchiveMessages;
use App\Models\ChannelModel;
use App\Models\ChatModel;
use App\Models\UserModel;
use CodeIgniter\Test\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

#[Group('integration')]
final class MessageHistoryIntegrationTest extends IntegrationTestCase
{
    public function testCursorHistoryCrossesTheArchiveBoundaryWithoutDuplicates(): void
    {
        $user = $this->createUser('historian');
        $channelId = $this->createChannel($user, 'history-room');
        $messages = new ChatModel();
        $ids = [];
        for ($sequence = 1; $sequence <= 6; ++$sequence) {
            $id = $messages->insertMsg('historian', "Message {$sequence}", $sequence * 100, $channelId);
            $this->assertIsInt($id);
            $ids[] = $id;
        }

        $this->assertSame(3, $messages->archiveMessagesBefore(350, $channelId, 2));
        $this->assertSame(3, $this->db->table('messages')->where('channel_id', $channelId)->countAllResults());
        $this->assertSame(3, $this->db->table('archived_messages')->where('channel_id', $channelId)->countAllResults());

        $first = $messages->getMessageHistory($channelId, limit: 2);
        $this->assertSame([$ids[5], $ids[4]], array_map('intval', array_column($first['messages'], 'id')));
        $this->assertTrue($first['pagination']['hasMore']);

        $second = $messages->getMessageHistory($channelId, $first['pagination']['nextBefore'], 2);
        $this->assertSame([$ids[3], $ids[2]], array_map('intval', array_column($second['messages'], 'id')));
        $this->assertTrue($second['pagination']['hasMore']);

        $third = $messages->getMessageHistory($channelId, $second['pagination']['nextBefore'], 2);
        $this->assertSame([$ids[1], $ids[0]], array_map('intval', array_column($third['messages'], 'id')));
        $this->assertFalse($third['pagination']['hasMore']);
        $this->assertNull($third['pagination']['nextBefore']);

        $response = $this->jsonRequest($user, 'get', "/api/v1/channels/{$channelId}/messages?before={$ids[4]}&limit=2");
        $response->assertOK();
        $this->assertSame([$ids[3], $ids[2]], array_map('intval', array_column($this->json($response)['messages'], 'id')));
    }

    public function testArchiveCommandIsBatchedScopedAndSafeToRerun(): void
    {
        $user = $this->createUser('archiver');
        $channelId = $this->createChannel($user, 'archive-room');
        $otherChannelId = $this->createChannel($user, 'other-room');
        $old = time() - (100 * 86_400);
        (new ChatModel())->insertMsg('archiver', 'Archive me', $old, $channelId);
        (new ChatModel())->insertMsg('archiver', 'Leave me', $old, $otherChannelId);

        command("messages:archive --older-than 90d --channel {$channelId} --batch-size 1");
        $this->seeInDatabase('archived_messages', ['channel_id' => $channelId, 'msg' => 'Archive me']);
        $this->seeInDatabase('messages', ['channel_id' => $otherChannelId, 'msg' => 'Leave me']);

        command("messages:archive --older-than 90d --channel {$channelId} --batch-size 1");
        $this->assertSame(1, $this->db->table('archived_messages')->where('channel_id', $channelId)->countAllResults());

        $this->assertSame(7_776_000, ArchiveMessages::parseAge('90d'));
        $this->assertNull(ArchiveMessages::parseAge('soon'));
        $this->assertSame($channelId, ArchiveMessages::parseChannel((string) $channelId));
        $this->assertFalse(ArchiveMessages::parseChannel('0'));
    }

    public function testChannelAndUserExportsIncludeLiveAndArchivedMessages(): void
    {
        $alice = $this->createUser('exportalice');
        $bob = $this->createUser('exportbob');
        $channelId = $this->createChannel($alice, 'export-room');
        (new ChannelModel())->join($channelId, (int) $bob['id']);
        $messages = new ChatModel();
        $messages->insertMsg('exportalice', 'Archived export', 100, $channelId);
        $messages->insertMsg('exportbob', 'Live export', 200, $channelId);
        $messages->archiveMessagesBefore(150, $channelId);

        $json = $this->jsonRequest($alice, 'get', "/api/v1/channels/{$channelId}/export?format=json");
        $this->assertSame(200, $json->response()->getStatusCode());
        $jsonBody = $this->downloadBody($json);
        $this->assertStringContainsString('channel-' . $channelId . '-messages.json', $json->response()->getHeaderLine('Content-Disposition'));
        $jsonRows = json_decode($jsonBody, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['Archived export', 'Live export'], array_column($jsonRows, 'msg'));

        $csv = $this->jsonRequest($bob, 'get', "/api/v1/channels/{$channelId}/export?format=csv");
        $this->assertSame(200, $csv->response()->getStatusCode());
        $csvBody = $this->downloadBody($csv);
        $this->assertStringStartsWith("id,channel_id,user,msg,time,archived\n", $csvBody);
        $this->assertStringContainsString('Archived export', $csvBody);
        $this->assertStringContainsString('Live export', $csvBody);

        $mine = $this->jsonRequest($alice, 'get', '/api/v1/users/me/export?format=json');
        $this->assertSame(200, $mine->response()->getStatusCode());
        $mineRows = json_decode($this->downloadBody($mine), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['Archived export'], array_column($mineRows, 'msg'));

        $outsider = $this->createUser('outsider');
        $denied = $this->jsonRequest($outsider, 'get', "/api/v1/channels/{$channelId}/export?format=json");
        $this->assertSame(403, $denied->response()->getStatusCode());
    }

    /** @return array<string, mixed> */
    private function createUser(string $username): array
    {
        $users = new UserModel();
        $id = $users->createUser($username, "{$username}@example.com", 'Password123!');
        $this->assertIsInt($id);
        $user = $users->findUserById($id);
        $this->assertIsArray($user);

        return $user;
    }

    /** @param array<string, mixed> $user */
    private function createChannel(array $user, string $slug): int
    {
        $id = (new ChannelModel())->createPublic(ucwords(str_replace('-', ' ', $slug)), $slug, null, (int) $user['id']);
        $this->assertIsInt($id);

        return $id;
    }

    /** @param array<string, mixed> $user */
    private function jsonRequest(array $user, string $method, string $path): TestResponse
    {
        return $this->loginAs($user)->withHeaders([
            'Origin' => 'http://localhost',
            'Accept' => 'application/json',
            'X-CSRF-TOKEN' => csrf_hash(),
        ])->call($method, $path);
    }

    /** @return array<string, mixed> */
    private function json(TestResponse $response): array
    {
        return json_decode($response->getJSON(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function downloadBody(TestResponse $response): string
    {
        $response->response()->buildHeaders();
        ob_start();
        $response->response()->sendBody();
        $body = ob_get_clean();
        $this->assertIsString($body);

        return $body;
    }
}
