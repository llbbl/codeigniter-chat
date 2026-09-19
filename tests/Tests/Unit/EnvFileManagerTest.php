<?php

namespace Tests\Unit;

use App\Services\EnvFileManager;
use CodeIgniter\Test\CIUnitTestCase;

final class EnvFileManagerTest extends CIUnitTestCase
{
    /** @var array<string, array<string, mixed>> */
    private array $schema = [
        'APP_PORT' => [
            'group' => 'Application',
            'type' => 'int',
            'default' => 8000,
            'description' => 'Application port.',
        ],
        'APP_SECRET' => [
            'group' => 'Application',
            'type' => 'string',
            'required' => true,
            'secret' => true,
            'description' => 'Application secret.',
        ],
        'OPTIONAL_VALUE' => [
            'group' => 'Application',
            'type' => 'string',
            'description' => 'Optional setting.',
        ],
    ];

    public function testExampleUsesDefaultsAndSafeSecretPlaceholder(): void
    {
        $example = (new EnvFileManager($this->schema))->renderExample();

        $this->assertStringContainsString("APP_PORT=8000\n", $example);
        $this->assertStringContainsString("APP_SECRET=CHANGE-ME\n", $example);
        $this->assertStringContainsString("# OPTIONAL_VALUE=\n", $example);
    }

    public function testDiffIncludesCommentedOptionalVariables(): void
    {
        $diff = (new EnvFileManager($this->schema))->diff("APP_PORT=8000\n# OPTIONAL_VALUE=\nEXTRA=value\n");

        $this->assertSame(['APP_SECRET'], $diff['missing']);
        $this->assertSame(['EXTRA'], $diff['unexpected']);
        $this->assertTrue($diff['stale']);
    }

    public function testDiffDetectsStaleGeneratedContentWithoutNameDrift(): void
    {
        $manager = new EnvFileManager($this->schema);
        $example = str_replace('APP_PORT=8000', 'APP_PORT=9000', $manager->renderExample());

        $diff = $manager->diff($example);

        $this->assertSame([], $diff['missing']);
        $this->assertSame([], $diff['unexpected']);
        $this->assertTrue($diff['stale']);
    }

    public function testDocumentationListsEverySchemaEntry(): void
    {
        $documentation = (new EnvFileManager($this->schema))->renderDocumentation();

        foreach (array_keys($this->schema) as $name) {
            $this->assertStringContainsString('`' . $name . '`', $documentation);
        }
    }
}
