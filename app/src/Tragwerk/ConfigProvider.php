<?php

declare(strict_types=1);

namespace Tragwerk;

use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\NormalizerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\DependencyFactory;
use Enqueue\Dbal\DbalContext;
use Interop\Queue\Context;
use Invoker\Invoker;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\I18n\Translator\Loader\Gettext;
use Laminas\I18n\Translator\TranslatorServiceFactory;
use Laminas\Stratigility\Middleware\ErrorHandler;
use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Authentication\Session\PhpSession;
use Mezzio\Authentication\UserRepositoryInterface;
use Mezzio\MiddlewareFactoryInterface;
use Mezzio\Router\FastRouteRouter;
use Mezzio\Session\Cache\CacheSessionPersistence;
use Mezzio\Session\SessionPersistenceInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\DoctrineDbalStore;
use Symfony\Component\Mailer\Transport as MailTransport;
use Tragwerk\Application\Cache\LockingCachePoolInterface;
use Tragwerk\Application\EventListener;
use Tragwerk\Application\Mail\Mailer;
use Tragwerk\Application\Response\HtmlResponseRenderer;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Template;
use Tragwerk\Application\Translator\LaminasTranslator;
use Tragwerk\Application\Translator\Translator;
use Tragwerk\Domain\Config\ConfigValidator;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Factory\Cli\DeployEnvironmentCommandFactory;
use Tragwerk\Factory\Cli\SyncEnvironmentDataCommandFactory;
use Tragwerk\Factory\Config\ConfigValidatorFactory;
use Tragwerk\Factory\Event\DispatcherFactory;
use Tragwerk\Factory\EventListener\Project\DeleteProjectDataFactory;
use Tragwerk\Factory\EventListener\SshKey\UpdateAuthorizedKeysFactory;
use Tragwerk\Factory\Git\BareRepositoryFactory;
use Tragwerk\Factory\Handler\Project\DownloadBuildHandlerFactory;
use Tragwerk\Factory\Handler\Project\TabHandlerFactory;
use Tragwerk\Factory\Middleware\MiddlewareFactory;
use Tragwerk\Factory\Queue\Handler\BuildEnvironmentFactory;
use Tragwerk\Factory\Queue\Handler\RunDeployEnvironmentFactory;
use Tragwerk\Factory\Queue\Handler\RunSyncEnvironmentDataFactory;
use Tragwerk\Factory\Valinor\DefaultMapperBuilderFactory;
use Tragwerk\Factory\Valinor\DefaultNormalizeBuilderFactory;
use Tragwerk\Factory\Valinor\TreeMapperFactory;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;

final readonly class ConfigProvider
{
    /** @return mixed[] */
    public function __invoke(): array
    {
        return new ConfigAggregator([
            $this->getAuthentication(...),
            $this->getLogger(...),
            $this->getQueue(...),
            $this->getDatabase(...),
            $this->getSession(...),
            $this->getAutoWire(...),
            $this->getRouter(...),
            $this->getRepository(...),
            $this->getTemplates(...),
            $this->getMiddlewares(...),
            $this->getTranslator(...),
            $this->getEvents(...),
            $this->getMail(...),
        ])->getMergedConfig();
    }

    /** @return mixed[] */
    private function getAutoWire(): array
    {
        return [
            'dependencies' => [
                'factories'          => [
                    Invoker::class        => Factory\Invoker\InvokerFactory::class,
                    BareRepository::class => BareRepositoryFactory::class,
                    Application\Handler\Project\TabHandler::class         => TabHandlerFactory::class,
                    Application\Handler\Project\DownloadBuildHandler::class => DownloadBuildHandlerFactory::class,
                    Application\Queue\Handler\BuildEnvironment::class         => BuildEnvironmentFactory::class,
                    Application\Queue\Handler\RunDeployEnvironment::class     => RunDeployEnvironmentFactory::class,
                    Application\Queue\Handler\RunSyncEnvironmentData::class   => RunSyncEnvironmentDataFactory::class,
                    Application\Cli\Command\DeployEnvironmentCommand::class   => DeployEnvironmentCommandFactory::class,
                    Application\Cli\Command\SyncEnvironmentDataCommand::class => SyncEnvironmentDataCommandFactory::class, // phpcs:ignore Generic.Files.LineLength.TooLong
                    EventListener\SshKey\UpdateAuthorizedKeys::class        => UpdateAuthorizedKeysFactory::class,
                    ConfigValidator::class                              => ConfigValidatorFactory::class,
                    EventListener\Project\DeleteProjectData::class     => DeleteProjectDataFactory::class,
                ],
                'abstract_factories' => [Factory\Invoker\InvokerAbstractFactory::class],
            ],
        ];
    }

    /** @return mixed[] */
    private function getRouter(): array
    {
        return [
            'dependencies' => [
                'factories' => [
                    FastRouteRouter::class => Factory\Router\FastRouteRouterFactory::class,
                ],
            ],
        ];
    }

    /** @return mixed[] */
    private function getTemplates(): array
    {
        return [
            'templates'    => [
                'extension' => 'phtml',
                'paths' => [
                    'page' => ['templates/app'],
                    'error' => ['templates/error'],
                    'partial' => ['templates/partials'],
                    'layout' => ['templates/layouts'],
                    'mail' => ['templates/mail'],
                ],
                'extensions' => [
                    Template\Extension\Authentication::class,
                    Template\Extension\Translator::class,
                    Template\Extension\Locale::class,
                    Template\Extension\Csrf::class,
                    Template\Extension\TeamContext::class,
                ],
            ],
            'dependencies' => [
                'aliases' => [
                    ResponseRenderer::class => HtmlResponseRenderer::class,
                ],
            ],
        ];
    }

    /** @return mixed[] */
    private function getLogger(): array
    {
        return [
            'dependencies' => [
                'delegators' => [
                    ErrorHandler::class => [Factory\Logger\ErrorHandlerDelegatorFactory::class],
                ],
                'factories'  => [
                    'app-logger' => Factory\Logger\AppLoggerFactory::class,
                ],
                'aliases'    => [LoggerInterface::class => 'app-logger'],
            ],
        ];
    }

    /** @return mixed[] */
    private function getQueue(): array
    {
        return [
            'dependencies' => [
                'aliases' => [
                    Application\Queue\Producer::class => Infrastructure\Queue\Producer::class,
                    Context::class => DbalContext::class,
                ],
                'factories' => [
                    DbalContext::class                       => Factory\Queue\Dbal\ContextFactory::class,
                    Application\Cli\Command\Worker::class   => Factory\Cli\WorkerFactory::class,
                ],
            ],
        ];
    }

    /** @return mixed[] */
    private function getMiddlewares(): array
    {
        return [
            'dependencies' => [
                'factories' => [
                    MiddlewareFactoryInterface::class => MiddlewareFactory::class,
                    MapperBuilder::class => DefaultMapperBuilderFactory::class,
                    NormalizerBuilder::class => DefaultNormalizeBuilderFactory::class,
                    TreeMapper::class => TreeMapperFactory::class,
                ],
            ],
        ];
    }

    /** @return mixed[] */
    private function getDatabase(): array
    {
        return [
            'dependencies' => [
                'factories' => [
                    Connection::class        => Factory\Database\Doctrine\ConnectionFactory::class,
                    Configuration::class     => Factory\Database\Doctrine\ConfigurationFactory::class,
                    DependencyFactory::class => Factory\Database\Doctrine\DependencyFactoryFactory::class,
                    LockFactory::class       => Factory\Lock\LockFactoryFactory::class,
                ],
            ],
        ];
    }

    /** @return mixed[] */
    private function getAuthentication(): array
    {
        return [
            'authentication' => [
                'username' => 'email',
                'password' => 'password',
                'redirect' => '/login',
            ],
            'dependencies'   => [
                'aliases' => [
                    AuthenticationInterface::class => PhpSession::class,
                    UserRepositoryInterface::class => UserRepository::class,
                ],
            ],
        ];
    }

    /** @return mixed[] */
    private function getSession(): array
    {
        return [
            'dependencies' => [
                'factories' => [
                    'session-cache'                    => static function (ContainerInterface $container) {
                        $db = $container->get(Connection::class);
                        assert($db instanceof Connection);
                        $psr6Cache = new DoctrineDbalAdapter($db, options: ['db_table' => 'sessions']);

                        $lockStore   = new DoctrineDbalStore($db, options: ['db_table' => 'session_locks']);
                        $lockFactory = new LockFactory($lockStore);

                        return new LockingCachePoolInterface($psr6Cache, $lockFactory);
                    },
                    SessionPersistenceInterface::class => static function (ContainerInterface $container) {
                        $sessionCache = $container->get('session-cache');
                        assert($sessionCache instanceof CacheItemPoolInterface);

                        return new CacheSessionPersistence(
                            $sessionCache,
                            'tragwerk-session',
                            cookieSecure: true,
                            cookieHttpOnly: true,
                        );
                    },
                ],
            ],
        ];
    }

    /** @return mixed[] */
    private function getRepository(): array
    {
        return [
            'dependencies' => [
                'aliases' => [
                    Domain\Repository\UserRepository::class => Infrastructure\Repository\UserRepository::class,
                    Domain\Repository\ServerRepository::class => Infrastructure\Repository\ServerRepository::class,
                    Domain\Repository\TeamRepository::class => Infrastructure\Repository\TeamRepository::class,
                    Domain\Repository\TeamInvitationRepository::class =>
                        Infrastructure\Repository\TeamInvitationRepository::class,
                    Domain\Repository\CredentialRepository::class =>
                        Infrastructure\Repository\CredentialRepository::class,
                    Domain\Repository\SetupJobRepository::class =>
                        Infrastructure\Repository\SetupJobRepository::class,
                    Domain\Repository\QueueMessageRepository::class =>
                        Infrastructure\Repository\QueueMessageRepository::class,
                    Domain\Repository\ProjectRepository::class =>
                        Infrastructure\Repository\ProjectRepository::class,
                    Domain\Repository\SshKeyRepository::class =>
                        Infrastructure\Repository\SshKeyRepository::class,
                    Domain\Repository\BuildLogRepository::class =>
                        Infrastructure\Repository\BuildLogRepository::class,
                    Domain\Repository\DeployJobRepository::class =>
                        Infrastructure\Repository\DeployJobRepository::class,
                    Domain\Repository\DomainRepository::class =>
                        Infrastructure\Repository\DomainRepository::class,
                ],
            ],
        ];
    }

    /** @return mixed[] */
    private function getTranslator(): array
    {
        return [
            'dependencies' => [
                'aliases' => [
                    Translator::class => LaminasTranslator::class,
                ],
                'factories' => [
                    \Laminas\I18n\Translator\Translator::class => TranslatorServiceFactory::class,
                ],
            ],
            'translator' => [
                'translation_files' => [
                    [
                        'type'        => Gettext::class,
                        'filename'    => 'data/translations/de_DE.mo',
                        'locale'      => 'de_DE',
                        'text_domain' => null,
                    ],
                    [
                        'type'        => Gettext::class,
                        'filename'    => 'data/translations/en_US.mo',
                        'locale'      => 'en_US',
                        'text_domain' => null,
                    ],
                ],
            ],
        ];
    }

    /** @return mixed[] */
    public function getEvents(): array
    {
        return [
            'dependencies' => [
                'aliases'   => [
                    EventDispatcherInterface::class => EventDispatcher::class,
                ],
                'factories' => [
                    EventDispatcher::class => DispatcherFactory::class,
                ],
            ],
            'events'       => [
                Event\UserRegistered::class => [
                    EventListener\User\UserRegisteredListener::class,
                    EventListener\User\CreateDefaultTeam::class,
                    EventListener\User\SendRegistrationMail::class,
                ],
                Event\CredentialCreated::class  => [EventListener\Credential\CreateCredential::class],
                Event\CredentialUpdated::class  => [EventListener\Credential\UpdateCredential::class],
                Event\CredentialDeleted::class  => [EventListener\Credential\DeleteCredential::class],
                Event\ServerCreated::class      => [EventListener\Server\CreateServer::class],
                Event\ServerUpdated::class      => [EventListener\Server\UpdateServer::class],
                Event\ServerDeleted::class      => [EventListener\Server\DeleteServer::class],
                Event\SetupJobScheduled::class  => [
                    EventListener\SetupJob\PersistSetupJob::class,
                    EventListener\SetupJob\ScheduleSetupJob::class,
                ],
                Event\TeamCreated::class         => [EventListener\Team\CreateTeam::class],
                Event\TeamUpdated::class         => [EventListener\Team\UpdateTeam::class],
                Event\TeamDeleted::class         => [EventListener\Team\DeleteTeam::class],
                Event\ProjectCreated::class      => [
                    EventListener\Project\CreateProject::class,
                    EventListener\Project\CreateGitRepository::class,
                ],
                Event\ProjectUpdated::class      => [EventListener\Project\UpdateProject::class],
                Event\ProjectDeleted::class      => [
                    EventListener\Project\DeleteProject::class,
                    EventListener\Project\DeleteGitRepository::class,
                    EventListener\Project\DeleteProjectData::class,
                ],
                Event\QueueMessageDeleted::class => [EventListener\Queue\DeleteQueueMessage::class],
                Event\SshKeyCreated::class       => [
                    EventListener\SshKey\PersistSshKey::class,
                    EventListener\SshKey\UpdateAuthorizedKeys::class,
                ],
                Event\SshKeyDeleted::class       => [
                    EventListener\SshKey\DeleteSshKey::class,
                    EventListener\SshKey\UpdateAuthorizedKeys::class,
                ],
                Event\DomainAdded::class       => [
                    EventListener\Domain\AddDomain::class,
                    EventListener\Domain\TriggerProjectRedeploy::class,
                ],
                Event\DomainDeleted::class     => [
                    EventListener\Domain\RemoveDomain::class,
                    EventListener\Domain\TriggerProjectRedeploy::class,
                ],
                Event\DomainSetPrimary::class  => [EventListener\Domain\SetDomainAsPrimary::class],
                Event\BuildLogCreated::class   => [EventListener\BuildLog\PersistBuildLog::class],
                Event\DeployJobCreated::class  => [EventListener\DeployJob\PersistDeployJob::class],
                Event\TeamInvitationCreated::class =>
                    [EventListener\Team\SendTeamInvitation::class],
                Event\UserSwitchedTeam::class => [EventListener\User\PersistLastActiveTeam::class],
            ],
        ];
    }

    /** @return mixed[] */
    private function getMail(): array
    {
        return [
            'dependencies' => [
                'factories' => [
                    Mailer::class => Factory\Mail\MailerFactory::class,
                    MailTransport\TransportInterface::class => Factory\Mail\Transport\TransportFactory::class,
                    MailTransport\SendmailTransport::class => Factory\Mail\Transport\SendmailFactory::class,
                    MailTransport\Smtp\EsmtpTransport::class => Factory\Mail\Transport\EsmtpFactory::class,
                ],
            ],
        ];
    }
}
