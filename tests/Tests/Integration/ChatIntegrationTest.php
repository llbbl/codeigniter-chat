<?php

namespace Tests\Integration;

use App\Models\UserModel;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\IntegrationTestCase;

#[Group('integration')]
final class ChatIntegrationTest extends IntegrationTestCase
{
    public function testApplicationMigrationsCreateTheAuthAndChatTables(): void
    {
        $this->assertTrue($this->db->tableExists('users'));
        $this->assertTrue($this->db->tableExists('messages'));
        $this->assertTrue($this->db->tableExists('csp_reports'));
        $this->assertTrue($this->db->tableExists('audit_log'));
    }

    public function testVersionedPostPersistsAndReturnsAMessageThroughTheRealRepository(): void
    {
        $userModel = new UserModel();
        $userId = $userModel->createUser('chatuser', 'chat@example.com', 'Password123!');
        $this->assertIsInt($userId);

        $user = $userModel->find($userId);
        $this->assertIsArray($user);

        $post = $this->loginAs($user)
            ->withHeaders(['Origin' => 'http://localhost'])
            ->call('post', '/api/v1/messages', [
                'message' => 'Persisted integration message',
                csrf_token() => csrf_hash(),
            ]);

        $post->assertOK();
        $this->assertSame(['success' => true], json_decode($post->getJSON(), true, flags: JSON_THROW_ON_ERROR));
        $this->assertMessageInDatabase('chatuser', 'Persisted integration message');

        $get = $this->loginAs($user)
            ->withHeaders(['Origin' => 'http://localhost'])
            ->call('get', '/api/v1/messages');

        $get->assertOK();
        $payload = json_decode($get->getJSON(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('chatuser', $payload['messages'][0]['user']);
        $this->assertSame('Persisted integration message', $payload['messages'][0]['msg']);
        $this->assertSame(1, $payload['pagination']['totalItems']);
    }

    public function testXmlEndpointFormatsRowsReturnedByTheRealRepository(): void
    {
        $userModel = new UserModel();
        $userId = $userModel->createUser('xmluser', 'xml@example.com', 'Password123!');
        $this->assertIsInt($userId);
        $user = $userModel->find($userId);
        $this->assertIsArray($user);

        $this->hasInDatabase('messages', [
            'user_id' => $userId,
            'msg' => 'Real XML row',
            'time' => 1_700_000_000,
        ]);

        $result = $this->loginAs($user)
            ->withHeaders(['Origin' => 'http://localhost'])
            ->call('get', '/api/v1/messages/xml');

        $result->assertOK();
        $result->assertHeader('Content-Type', 'text/xml');
        $this->assertStringContainsString('<author>xmluser</author>', $result->getBody());
        $this->assertStringContainsString('<text>Real XML row</text>', $result->getBody());
    }
}
