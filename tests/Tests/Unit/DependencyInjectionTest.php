<?php

namespace Tests\Unit;

use App\Contracts\AuditLogger;
use App\Contracts\AuditLogRepository;
use App\Contracts\ChatFormatter;
use App\Contracts\ChatRepository;
use App\Contracts\ChannelRepository;
use App\Contracts\CspReportRepository;
use App\Contracts\UserRepository;
use App\Controllers\AuditLog;
use App\Controllers\Auth;
use App\Controllers\Chat;
use App\Controllers\CspReport;
use App\Core\Application;
use App\Services\NullAuditLogger;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use ReflectionClass;
use ReflectionNamedType;

/**
 * @internal
 */
final class DependencyInjectionTest extends CIUnitTestCase
{
    public function testServicesBindContractsToImplementations(): void
    {
        $this->assertInstanceOf(ChatRepository::class, Services::chatRepository(false));
        $this->assertInstanceOf(ChannelRepository::class, Services::channelRepository(false));
        $this->assertInstanceOf(UserRepository::class, Services::userRepository(false));
        $this->assertInstanceOf(CspReportRepository::class, Services::cspReportRepository(false));
        $this->assertInstanceOf(AuditLogRepository::class, Services::auditLogRepository(false));
        $this->assertInstanceOf(ChatFormatter::class, Services::chatFormatter(false));
        $this->assertInstanceOf(AuditLogger::class, Services::auditLogger(false));
    }

    public function testTestingEnvironmentUsesNullAuditLogger(): void
    {
        $this->assertInstanceOf(NullAuditLogger::class, Services::auditLogger(false));
    }

    public function testApplicationOverridesFrameworkControllerConstruction(): void
    {
        $this->assertInstanceOf(Application::class, Services::codeigniter(null, false));
    }

    public function testControllerDependenciesAreRequiredInterfaces(): void
    {
        foreach ([Chat::class, Auth::class, CspReport::class, AuditLog::class] as $controllerClass) {
            $constructor = (new ReflectionClass($controllerClass))->getConstructor();
            $this->assertNotNull($constructor);

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();
                $this->assertFalse($parameter->isOptional());
                $this->assertInstanceOf(ReflectionNamedType::class, $type);
                $this->assertTrue(interface_exists($type->getName()));
            }
        }
    }
}
