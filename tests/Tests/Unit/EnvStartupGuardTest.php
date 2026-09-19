<?php

namespace Tests\Unit;

use App\Exceptions\EnvValidationException;
use App\Services\EnvStartupGuard;
use App\Services\EnvValidator;
use CodeIgniter\Test\CIUnitTestCase;

final class EnvStartupGuardTest extends CIUnitTestCase
{
    public function testValidConfigurationAllowsStartup(): void
    {
        $guard = new EnvStartupGuard(new EnvValidator([], static fn (string $name): null => null));

        $guard->assertValid();

        $this->addToAssertionCount(1);
    }

    public function testInvalidConfigurationBecomesAServiceUnavailableError(): void
    {
        $validator = new EnvValidator(
            ['APP_URL' => ['type' => 'url', 'required' => true]],
            static fn (string $name): null => null,
        );

        try {
            (new EnvStartupGuard($validator))->assertValid();
            $this->fail('Expected invalid configuration to stop startup.');
        } catch (EnvValidationException $exception) {
            $this->assertSame(503, $exception->getCode());
            $this->assertSame(['APP_URL is required.'], $exception->errors);
            $this->assertStringContainsString('Environment configuration is invalid', $exception->getMessage());
        }
    }
}
