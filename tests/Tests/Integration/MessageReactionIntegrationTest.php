<?php

namespace Tests\Integration;

use App\Models\ChatModel;
use App\Models\MessageReactionModel;
use App\Models\UserModel;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

#[Group('integration')]
#[Group('message-reactions')]
final class MessageReactionIntegrationTest extends IntegrationTestCase
{
    public function testRepositoryAggregatesUsersAndPreventsDuplicateReactions(): void
    {
        [$alice, $bob] = $this->users();
        $messageId = (new ChatModel())->insertMsg('alice', 'React to this 👋', time());
        $this->assertIsInt($messageId);
        $reactions = new MessageReactionModel();

        $this->assertTrue($reactions->add($messageId, (int) $alice['id'], '👍'));
        $this->assertTrue($reactions->add($messageId, (int) $alice['id'], '👍'));
        $this->assertTrue($reactions->add($messageId, (int) $bob['id'], '👍'));

        $this->assertSame([[
            'emoji' => '👍',
            'count' => 2,
            'users' => ['alice', 'bob'],
            'reacted_by_current_user' => true,
        ]], $reactions->forMessage($messageId, (int) $alice['id']));
        $this->assertSame(2, $reactions->where('message_id', $messageId)->countAllResults());

        $this->assertTrue($reactions->remove($messageId, (int) $alice['id'], '👍'));
        $this->assertSame(['bob'], $reactions->forMessage($messageId, (int) $alice['id'])[0]['users']);
    }

    public function testAuthenticatedApiCreatesListsAndDeletesAReaction(): void
    {
        [$alice] = $this->users();
        $messageId = (new ChatModel())->insertMsg('alice', 'API reaction target', time());
        $this->assertIsInt($messageId);

        $created = $this->loginAs($alice)
            ->withHeaders([
                'Origin' => 'http://localhost',
                'Content-Type' => 'application/json',
                'X-CSRF-TOKEN' => csrf_hash(),
            ])
            ->withBody(json_encode(['emoji' => '🎉'], JSON_THROW_ON_ERROR))
            ->call('post', "/api/v1/messages/{$messageId}/reactions");
        $created->assertStatus(201);
        $this->assertNotSame('', $created->response()->getHeaderLine('X-CSRF-TOKEN'));
        $this->assertStringContainsString('no-store', $created->response()->getHeaderLine('Cache-Control'));
        $payload = json_decode($created->getJSON(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('🎉', $payload['reaction']['emoji']);
        $this->assertSame(1, $payload['reaction']['count']);
        $this->assertTrue($payload['reaction']['reacted_by_current_user']);

        $listed = $this->call('get', "/api/v1/messages/{$messageId}/reactions");
        $listed->assertOK();
        $this->assertSame(['alice'], json_decode($listed->getJSON(), true, flags: JSON_THROW_ON_ERROR)[0]['users']);

        $deleted = $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])
            ->call('delete', "/api/v1/messages/{$messageId}/reactions/" . rawurlencode('🎉'));
        $deleted->assertStatus(204);
        $this->assertSame([], (new MessageReactionModel())->forMessage($messageId, (int) $alice['id']));
    }

    public function testApiRejectsInvalidEmojiAndUnknownMessages(): void
    {
        [$alice] = $this->users();

        $invalid = $this->loginAs($alice)
            ->withHeaders([
                'Origin' => 'http://localhost',
                'Content-Type' => 'application/json',
                'X-CSRF-TOKEN' => csrf_hash(),
            ])
            ->withBody(json_encode(['emoji' => 'not emoji'], JSON_THROW_ON_ERROR))
            ->call('post', '/api/v1/messages/999999/reactions');
        $invalid->assertStatus(404);

        $messageId = (new ChatModel())->insertMsg('alice', 'Validation target', time());
        $invalid = $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])
            ->withBody(json_encode(['emoji' => 'not emoji'], JSON_THROW_ON_ERROR))
            ->call('post', "/api/v1/messages/{$messageId}/reactions");
        $invalid->assertStatus(422);
    }

    /** @return list<array<string, mixed>> */
    private function users(): array
    {
        $model = new UserModel();
        $users = [];
        foreach (['alice', 'bob'] as $username) {
            $id = $model->createUser($username, "{$username}@example.test", 'Password123!');
            $this->assertIsInt($id);
            $user = $model->find($id);
            $this->assertIsArray($user);
            $users[] = $user;
        }

        return $users;
    }
}
