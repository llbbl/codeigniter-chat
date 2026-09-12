<?php

namespace Config;

use App\Contracts\AuditLogger as AuditLoggerContract;
use App\Contracts\AuditLogRepository;
use App\Contracts\ChatFormatter as ChatFormatterContract;
use App\Contracts\ChatRepository;
use App\Contracts\CspReportRepository;
use App\Contracts\UserRepository;
use App\Controllers\AuditLog;
use App\Controllers\Auth;
use App\Controllers\Chat;
use App\Controllers\CspReport;
use App\Controllers\Home;
use App\Core\Application;
use App\Libraries\ErrorHandler;
use App\Models\AuditLogModel;
use App\Models\ChatModel;
use App\Models\CspReportModel;
use App\Models\UserModel;
use App\Services\AuditLogger;
use App\Services\ChatFormatter;
use App\Services\CorrelationId;
use App\Services\NullAuditLogger;
use CodeIgniter\CodeIgniter;
use CodeIgniter\Config\BaseService;
use CodeIgniter\Controller;

/**
 * Application composition root.
 *
 * Dependency injection keeps construction here and behavior in consumers:
 *
 * 1. Consumers require contracts in their constructors.
 * 2. Service methods bind those contracts to concrete implementations.
 * 3. Application::createController() asks this class to assemble controllers.
 * 4. Tests pass interface stubs directly or inject a repository service when
 *    exercising the complete HTTP pipeline.
 *
 * `$getShared` controls lifetime. Shared repositories are reused during one
 * request; passing false creates a fresh implementation. Environment-specific
 * bindings belong here too: tests receive NullAuditLogger, while other
 * environments receive the database-backed AuditLogger.
 */
class Services extends BaseService
{
    public static function codeigniter(?App $config = null, bool $getShared = true): CodeIgniter
    {
        if ($getShared) {
            return static::getSharedInstance('codeigniter', $config);
        }

        return new Application($config ?? config(App::class));
    }

    /** @param class-string<Controller> $controllerClass */
    public static function controller(string $controllerClass): Controller
    {
        $controllerClass = ltrim($controllerClass, '\\');

        return match ($controllerClass) {
            Home::class => new Home(),
            Chat::class => new Chat(static::chatRepository(), static::chatFormatter()),
            Auth::class => new Auth(static::userRepository(), static::auditLogger()),
            CspReport::class => new CspReport(static::cspReportRepository()),
            AuditLog::class => new AuditLog(static::auditLogRepository()),
            default => throw new \InvalidArgumentException('Controller is not registered: ' . $controllerClass),
        };
    }

    public static function chatRepository(bool $getShared = true): ChatRepository
    {
        if ($getShared) {
            return static::getSharedInstance('chatRepository');
        }

        return new ChatModel();
    }

    public static function userRepository(bool $getShared = true): UserRepository
    {
        if ($getShared) {
            return static::getSharedInstance('userRepository');
        }

        return new UserModel();
    }

    public static function chatFormatter(bool $getShared = true): ChatFormatterContract
    {
        if ($getShared) {
            return static::getSharedInstance('chatFormatter');
        }

        return new ChatFormatter();
    }

    public static function cspReportRepository(bool $getShared = true): CspReportRepository
    {
        if ($getShared) {
            return static::getSharedInstance('cspReportRepository');
        }

        return new CspReportModel();
    }

    public static function auditLogRepository(bool $getShared = true): AuditLogRepository
    {
        if ($getShared) {
            return static::getSharedInstance('auditLogRepository');
        }

        return new AuditLogModel();
    }

    public static function auditLogger(bool $getShared = true): AuditLoggerContract
    {
        if ($getShared) {
            return static::getSharedInstance('auditLogger');
        }

        if (ENVIRONMENT === 'testing') {
            return new NullAuditLogger();
        }

        return new AuditLogger(new AuditLogModel(), service('request'));
    }

    public static function correlationId(bool $getShared = true): CorrelationId
    {
        if ($getShared) {
            return static::getSharedInstance('correlationId');
        }

        return new CorrelationId(service('request'));
    }

    public static function errorHandler(bool $getShared = true): ErrorHandler
    {
        if ($getShared) {
            return static::getSharedInstance('errorHandler');
        }

        return new ErrorHandler(service('request'), service('response'), service('correlationId'));
    }
}
