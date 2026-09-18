<?php

namespace Config;

use App\Contracts\AuditLogger as AuditLoggerContract;
use App\Contracts\AuditLogRepository;
use App\Contracts\ChannelRepository;
use App\Contracts\ChatFormatter as ChatFormatterContract;
use App\Contracts\ChatRepository;
use App\Contracts\CspReportRepository;
use App\Contracts\PushSubscriptionRepository;
use App\Contracts\ReactionRepository;
use App\Contracts\UserRepository;
use App\Contracts\WebhookHttpClient;
use App\Contracts\WebhookRepository;
use App\Controllers\Api\V1\ChannelsController;
use App\Controllers\Api\V1\MessagesController;
use App\Controllers\Api\V1\PushSubscriptionsController;
use App\Controllers\Api\V1\ReactionsController;
use App\Controllers\Api\V1\WebhooksController;
use App\Controllers\ApiDocs;
use App\Controllers\AuditLog;
use App\Controllers\Auth;
use App\Controllers\Chat;
use App\Controllers\CspReport;
use App\Controllers\Home;
use App\Controllers\Profile;
use App\Core\Application;
use App\Libraries\ErrorHandler;
use App\Models\AuditLogModel;
use App\Models\ChannelModel;
use App\Models\ChatModel;
use App\Models\CspReportModel;
use App\Models\MessageReactionModel;
use App\Models\PushSubscriptionModel;
use App\Models\UserModel;
use App\Models\WebhookModel;
use App\Services\AuditLogger;
use App\Services\ChatFormatter;
use App\Services\CodeIgniterWebhookHttpClient;
use App\Services\CorrelationId;
use App\Services\NullAuditLogger;
use App\Services\WebhookDeliveryService;
use App\Services\WebhookUrlValidator;
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
            ApiDocs::class => new ApiDocs(),
            Chat::class => new Chat(static::chatRepository(), static::chatFormatter()),
            MessagesController::class => new MessagesController(static::chatRepository(), static::chatFormatter()),
            ChannelsController::class => new ChannelsController(static::channelRepository(), static::chatRepository(), static::userRepository()),
            PushSubscriptionsController::class => new PushSubscriptionsController(static::pushSubscriptionRepository()),
            ReactionsController::class => new ReactionsController(static::reactionRepository(), static::channelRepository()),
            WebhooksController::class => new WebhooksController(static::webhookRepository(), static::webhookUrlValidator()),
            Auth::class => new Auth(static::userRepository(), static::auditLogger()),
            Profile::class => new Profile(static::userRepository()),
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

        return new ChatModel(webhooks: static::webhookRepository());
    }

    public static function channelRepository(bool $getShared = true): ChannelRepository
    {
        if ($getShared) {
            return static::getSharedInstance('channelRepository');
        }

        return new ChannelModel(webhooks: static::webhookRepository());
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

    public static function pushSubscriptionRepository(bool $getShared = true): PushSubscriptionRepository
    {
        if ($getShared) {
            return static::getSharedInstance('pushSubscriptionRepository');
        }

        return new PushSubscriptionModel();
    }

    public static function reactionRepository(bool $getShared = true): ReactionRepository
    {
        if ($getShared) {
            return static::getSharedInstance('reactionRepository');
        }

        return new MessageReactionModel(webhooks: static::webhookRepository());
    }

    public static function webhookRepository(bool $getShared = true): WebhookRepository
    {
        if ($getShared) {
            return static::getSharedInstance('webhookRepository');
        }

        return new WebhookModel();
    }

    public static function webhookHttpClient(bool $getShared = true): WebhookHttpClient
    {
        if ($getShared) {
            return static::getSharedInstance('webhookHttpClient');
        }

        return new CodeIgniterWebhookHttpClient(parent::curlrequest([], null, null, false));
    }

    public static function webhookUrlValidator(bool $getShared = true): WebhookUrlValidator
    {
        if ($getShared) {
            return static::getSharedInstance('webhookUrlValidator');
        }

        $environment = strtolower((string) ($_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'production'));

        return new WebhookUrlValidator(allowHttpLoopback: in_array($environment, ['development', 'testing'], true));
    }

    public static function webhookDeliveryService(bool $getShared = true): WebhookDeliveryService
    {
        if ($getShared) {
            return static::getSharedInstance('webhookDeliveryService');
        }

        return new WebhookDeliveryService(
            static::webhookRepository(),
            static::webhookHttpClient(),
            urlValidator: static::webhookUrlValidator(),
        );
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
