<?php

namespace Tests\Integration;

use App\Models\ChannelModel;
use App\Models\ChatModel;
use App\Models\UserModel;
use CodeIgniter\Test\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

#[Group('integration')]
final class ChannelApiIntegrationTest extends IntegrationTestCase
{
    public function testAuthenticatedUserListsGeneralAndCanCreateAValidatedChannel(): void
    {
        $alice = $this->createUser('alice');

        $listed = $this->jsonRequest($alice, 'get', '/api/v1/channels');
        $listed->assertOK();
        $payload = $this->json($listed);
        $this->assertSame('general', $payload['channels'][0]['slug']);
        $this->assertTrue($payload['channels'][0]['is_member']);

        $invalid = $this->jsonRequest($alice, 'post', '/api/v1/channels', [
            'name' => 'Invalid',
            'slug' => 'No Spaces',
        ]);
        $this->assertSame(400, $invalid->response()->getStatusCode());

        $created = $this->jsonRequest($alice, 'post', '/api/v1/channels', [
            'name' => 'Project Room',
            'slug' => 'project-room',
            'topic' => 'Planning',
        ]);
        $this->assertSame(201, $created->response()->getStatusCode());
        $channel = $this->json($created)['channel'];
        $this->assertSame('project-room', $channel['slug']);
        $this->seeInDatabase('channel_members', ['channel_id' => $channel['id'], 'user_id' => $alice['id']]);
    }

    public function testPublicMembershipAndGeneralLeaveProtection(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $channels = new ChannelModel();
        $channelId = $channels->createPublic('Team', 'team-room', null, (int) $alice['id']);
        $this->assertIsInt($channelId);

        $discovered = $this->json($this->jsonRequest($bob, 'get', '/api/v1/channels'))['channels'];
        $team = array_values(array_filter($discovered, static fn (array $channel): bool => $channel['id'] === $channelId))[0];
        $this->assertFalse($team['is_member']);

        $joined = $this->jsonRequest($bob, 'post', "/api/v1/channels/{$channelId}/join");
        $joined->assertOK();
        $this->assertNotSame('', $joined->response()->getHeaderLine('X-CSRF-TOKEN'));
        $this->assertTrue($channels->isMember($channelId, (int) $bob['id']));

        $left = $this->jsonRequest($bob, 'post', "/api/v1/channels/{$channelId}/leave");
        $left->assertOK();
        $this->assertFalse($channels->isMember($channelId, (int) $bob['id']));

        $channels->ensureGeneralMembership((int) $bob['id']);
        $generalLeave = $this->jsonRequest($bob, 'post', '/api/v1/channels/' . $channels->generalChannelId() . '/leave');
        $this->assertSame(400, $generalLeave->response()->getStatusCode());
        $this->assertTrue($channels->isMember($channels->generalChannelId(), (int) $bob['id']));
    }

    public function testOnlyCreatorCanRenameSetTopicAndArchive(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $channels = new ChannelModel();
        $channelId = $channels->createPublic('Team', 'team-room', null, (int) $alice['id']);
        $this->assertIsInt($channelId);
        $channels->join($channelId, (int) $bob['id']);

        $denied = $this->jsonRequest($bob, 'patch', "/api/v1/channels/{$channelId}", ['name' => 'Hijacked']);
        $this->assertSame(403, $denied->response()->getStatusCode());

        $updated = $this->jsonRequest($alice, 'patch', "/api/v1/channels/{$channelId}", [
            'name' => 'Renamed Team',
            'slug' => 'renamed-team',
            'topic' => 'Updated topic',
            'archived' => true,
        ]);
        $updated->assertOK();
        $channel = $this->json($updated)['channel'];
        $this->assertSame('renamed-team', $channel['slug']);
        $this->assertSame('Updated topic', $channel['topic']);
        $this->assertNotNull($channel['archived_at']);
    }

    public function testDirectMessageCreationIsIdempotentAndPrivate(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $mallory = $this->createUser('mallory');

        $first = $this->jsonRequest($alice, 'post', '/api/v1/dms', ['user_id' => $bob['id']]);
        $second = $this->jsonRequest($bob, 'post', '/api/v1/dms', ['username' => 'alice']);
        $this->assertSame(201, $first->response()->getStatusCode());
        $this->assertSame($this->json($first)['channel']['id'], $this->json($second)['channel']['id']);

        $channelId = (int) $this->json($first)['channel']['id'];
        $malloryChannels = $this->json($this->jsonRequest($mallory, 'get', '/api/v1/channels'))['channels'];
        $this->assertNotContains($channelId, array_column($malloryChannels, 'id'));

        $messageId = (new ChatModel())->insertMsg('alice', 'Private reaction target', time(), $channelId);
        $this->assertIsInt($messageId);
        $denied = $this->jsonRequest($mallory, 'get', "/api/v1/channels/{$channelId}/messages");
        $this->assertSame(403, $denied->response()->getStatusCode());
        $reactionDenied = $this->jsonRequest($mallory, 'get', "/api/v1/messages/{$messageId}/reactions");
        $this->assertSame(404, $reactionDenied->response()->getStatusCode());
    }

    public function testChannelMessagesRequireMembershipAndAreChannelScoped(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');
        $channels = new ChannelModel();
        $channelId = $channels->createPublic('Private Enough', 'private-enough', null, (int) $alice['id']);
        $this->assertIsInt($channelId);

        $denied = $this->jsonRequest($bob, 'post', "/api/v1/channels/{$channelId}/messages", ['message' => 'No access']);
        $this->assertSame(403, $denied->response()->getStatusCode());

        $this->jsonRequest($bob, 'post', "/api/v1/channels/{$channelId}/join")->assertOK();
        $created = $this->jsonRequest($bob, 'post', "/api/v1/channels/{$channelId}/messages", ['message' => 'Channel only']);
        $this->assertSame(201, $created->response()->getStatusCode());
        $this->assertSame($channelId, $this->json($created)['message']['channel_id']);

        $listed = $this->jsonRequest($bob, 'get', "/api/v1/channels/{$channelId}/messages?page=1&per_page=10");
        $listed->assertOK();
        $payload = $this->json($listed);
        $this->assertCount(1, $payload['messages']);
        $this->assertSame('Channel only', $payload['messages'][0]['msg']);

        $searched = $this->jsonRequest($bob, 'get', "/api/v1/channels/{$channelId}/messages/search?text=Channel%20only");
        $searched->assertOK();
        $this->assertSame('Channel only', $this->json($searched)['messages'][0]['msg']);

        $general = $this->jsonRequest($bob, 'get', '/api/v1/messages');
        $general->assertOK();
        $this->assertSame([], $this->json($general)['messages']);
    }

    /** @return array<string, mixed> */
    private function createUser(string $username): array
    {
        $model = new UserModel();
        $id = $model->createUser($username, "{$username}@example.com", 'Password123!');
        $this->assertIsInt($id);
        $user = $model->findUserById($id);
        $this->assertIsArray($user);

        return $user;
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $payload */
    private function jsonRequest(array $user, string $method, string $path, array $payload = []): TestResponse
    {
        $test = $this->loginAs($user)->withHeaders([
            'Origin' => 'http://localhost',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-CSRF-TOKEN' => csrf_hash(),
        ]);
        if ($payload !== []) {
            $test->withBody(json_encode($payload, JSON_THROW_ON_ERROR));
        }

        return $test->call($method, $path);
    }

    /** @return array<string, mixed> */
    private function json(TestResponse $response): array
    {
        return json_decode($response->getJSON(), true, flags: JSON_THROW_ON_ERROR);
    }
}
