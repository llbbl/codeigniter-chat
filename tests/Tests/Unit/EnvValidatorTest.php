<?php

namespace Tests\Unit;

use App\Services\EnvValidator;
use CodeIgniter\Test\CIUnitTestCase;

final class EnvValidatorTest extends CIUnitTestCase
{
    public function testMissingRequiredValueFails(): void
    {
        $errors = $this->validator(['TOKEN' => ['type' => 'string', 'required' => true]])->validate();

        $this->assertSame(['TOKEN is required.'], $errors);
    }

    public function testWrongTypeFails(): void
    {
        $errors = $this->validator(['ENABLED' => ['type' => 'bool']], ['ENABLED' => 'sometimes'])->validate();

        $this->assertSame(['ENABLED must be a boolean.'], $errors);
    }

    public function testOutOfRangeIntegerFails(): void
    {
        $errors = $this->validator(
            ['PORT' => ['type' => 'int', 'min' => 1, 'max' => 65535]],
            ['PORT' => '70000'],
        )->validate();

        $this->assertSame(['PORT must be at most 65535.'], $errors);
    }

    public function testEnumMismatchFails(): void
    {
        $errors = $this->validator(
            ['DRIVER' => ['type' => 'enum', 'values' => ['MySQLi', 'SQLite3']]],
            ['DRIVER' => 'mysql'],
        )->validate();

        $this->assertSame(['DRIVER must be one of: MySQLi, SQLite3.'], $errors);
    }

    public function testForbiddenSecretFailsWithoutExposingItsValue(): void
    {
        $errors = $this->validator(
            ['TOKEN' => ['type' => 'string', 'secret' => true, 'forbidden_values' => ['change-me']]],
            ['TOKEN' => 'CHANGE-ME'],
        )->validate();

        $this->assertSame(['TOKEN contains a forbidden placeholder value.'], $errors);
        $this->assertStringNotContainsString('CHANGE-ME', implode(' ', $errors));
    }

    public function testConditionalRequirementUsesAnotherSchemaValue(): void
    {
        $schema = [
            'DRIVER' => ['type' => 'enum', 'values' => ['MySQLi', 'SQLite3']],
            'PASSWORD' => ['type' => 'string', 'required_when' => ['DRIVER' => 'MySQLi']],
        ];

        $this->assertSame(
            ['PASSWORD is required.'],
            $this->validator($schema, ['DRIVER' => 'MySQLi'])->validate(),
        );
        $this->assertSame([], $this->validator($schema, ['DRIVER' => 'SQLite3'])->validate());
    }

    /**
     * @param array<string, array<string, mixed>> $schema
     * @param array<string, mixed>                $values
     */
    private function validator(array $schema, array $values = []): EnvValidator
    {
        return new EnvValidator($schema, static fn (string $name): mixed => $values[$name] ?? null);
    }
}
