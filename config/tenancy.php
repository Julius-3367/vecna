<?php

declare(strict_types=1);

return [
    'tenant_model' => \App\Models\Tenant::class,
    'id_generator' => \Stancl\Tenancy\UUIDGenerator::class,

    'domain_model' => \App\Models\Domain::class,

    /**
     * Database configuration
     */
    'database' => [
        'based_on' => env('DB_CONNECTION', 'pgsql'),

        'template_tenant_connection' => 'pgsql',

        'prefix' => 'tenant_',

        'suffix' => '',

        'managers' => [
            'pgsql' => \Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],
    ],

    /**
     * Features enabled for tenants
     */
    'features' => [
        \Stancl\Tenancy\Features\UserImpersonation::class,
        \Stancl\Tenancy\Features\TenantConfig::class,
        \Stancl\Tenancy\Features\UniversalRoutes::class,
    ],

    /**
     * Route middleware
     */
    'middleware' => [
        'web' => [
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
        ],
        'api' => [
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
        ],
        'universal' => [
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain::class,
        ],
    ],

    /**
     * Central domains (won't be treated as tenants)
     */
    'central_domains' => [
        'vecna.test',
        '127.0.0.1',
        'localhost',
    ],

    /**
     * Bootstrappers executed when tenancy is initialized
     */
    'bootstrappers' => [
        \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    /**
     * Storage paths
     */
    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
            's3',
        ],
    ],

    /**
     * Queue configuration
     */
    'queue_database_creation' => false,

    'queues' => [
        'connections' => [
            'sync',
            'redis',
        ],
    ],
];
