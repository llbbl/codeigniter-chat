<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Helpers\TextHelper;

/**
 * @internal
 */
final class TextHelperTest extends CIUnitTestCase
{
    public function testTruncate(): void
    {
        // Test with string shorter than the limit
        $shortString = 'This is a short string';
        $this->assertEquals(
            $shortString,
            TextHelper::truncate($shortString, 100),
            'Short strings should not be truncated'
        );

        // Test with string longer than the limit
        $longString = 'This is a very long string that should be truncated because it exceeds the limit';
        $expected = 'This is a very long string that should be truncated because ...';
        $this->assertEquals(
            $expected,
            TextHelper::truncate($longString, 60),
            'Long strings should be truncated to the specified length plus ellipsis'
        );

        // Test with custom ellipsis
        $this->assertEquals(
            'This is a very long string that should be truncated because [...]',
            TextHelper::truncate($longString, 60, '[...]'),
            'Custom ellipsis should be appended to truncated strings'
        );
    }

    public function testTitleCase(): void
    {
        // Test with lowercase string
        $this->assertEquals(
            'This Is A Test String',
            TextHelper::titleCase('this is a test string'),
            'Lowercase strings should be converted to title case'
        );

        // Test with uppercase string
        $this->assertEquals(
            'This Is A Test String',
            TextHelper::titleCase('THIS IS A TEST STRING'),
            'Uppercase strings should be converted to title case'
        );

        // Test with mixed case string
        $this->assertEquals(
            'This Is A Test String',
            TextHelper::titleCase('ThIs iS a TeSt StRiNg'),
            'Mixed case strings should be converted to title case'
        );
    }
}
