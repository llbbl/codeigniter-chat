<?php

namespace Tests\Unit;

use App\Helpers\ChatHelper;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ChatHelperTest extends CIUnitTestCase
{
    /**
     * @var list<array{id: int, user: string, msg: string, time: int}>
     */
    private array $messages = [[
        'id' => 1,
        'user' => '<script>alert("author")</script>',
        'msg' => '<img src=x onerror=alert("message")>',
        'time' => 1,
    ]];

    public function testJsonFormatEscapesUserControlledFields(): void
    {
        $data = ChatHelper::formatAsJson($this->messages);

        $this->assertSame('&lt;script&gt;alert(&quot;author&quot;)&lt;/script&gt;', $data['messages'][0]['user']);
        $this->assertSame('&lt;img src=x onerror=alert(&quot;message&quot;)&gt;', $data['messages'][0]['msg']);
    }

    public function testXmlFormatEscapesUserControlledFields(): void
    {
        $xml = ChatHelper::formatAsXml($this->messages);

        $this->assertStringContainsString('<author>&lt;script&gt;alert(&quot;author&quot;)&lt;/script&gt;</author>', $xml);
        $this->assertStringContainsString('<text>&lt;img src=x onerror=alert(&quot;message&quot;)&gt;</text>', $xml);
    }
}
