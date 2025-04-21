<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 19:42:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'aiku'), '_').'_'.env('APP_ENV').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['horizon'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent'        => (int)env('HORIZON_RECENT_TRIM', 60),
        'pending'       => (int)env('HORIZON_RECENT_TRIM', 60),
        'completed'     => (int)env('HORIZON_RECENT_TRIM', 60),
        'recent_failed' => (int)env('HORIZON_FAILED_TRIM', 480),
        'failed'        => (int)env('HORIZON_FAILED_TRIM', 10080),
        'monitored'     => (int)env('HORIZON_FAILED_TRIM', 480),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 2048,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [

        'normal'           => [
            'connection'      => 'redis',
            'queue'           => ['default'],
            'balance'         => 'auto',
            'maxProcesses'    => 1,
            'maxTime'         => 0,
            'maxJobs'         => 0,
            'memory'          => 1280,
            'tries'           => 10,
            'timeout'         => 3600,
            'retry_after'     => 2,
            'nice'            => 0,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
        ],
        'aurora'           => [
            'connection'      => 'redis',
            'queue'           => ['aurora'],
            'balance'         => 'auto',
            'maxProcesses'    => 1,
            'maxTime'         => 0,
            'maxJobs'         => 0,
            'memory'          => 1280,
            'tries'           => 2,
            'timeout'         => 36000,
            'retry_after'     => 2,
            'nice'            => 0,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
        ],
        'sales'            => [
            'connection'      => 'redis',
            'queue'           => ['sales'],
            'balance'         => 'auto',
            'maxProcesses'    => 1,
            'maxTime'         => 0,
            'maxJobs'         => 0,
            'memory'          => 1280,
            'tries'           => 10,
            'timeout'         => 3600,
            'retry_after'     => 120,
            'nice'            => 0,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
        ],
        'universal-search' => [
            'connection'      => 'redis',
            'queue'           => ['universal-search'],
            'balance'         => 'auto',
            'maxProcesses'    => 20,
            'maxTime'         => 0,
            'maxJobs'         => 0,
            'memory'          => 1280,
            'tries'           => 10,
            'timeout'         => 3600,
            'retry_after'     => 120,
            'nice'            => 0,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
        ],
        'urgent'           => [
            'connection'      => 'redis',
            'queue'           => ['urgent'],
            'balance'         => 'auto',
            'maxProcesses'    => 20,
            'maxTime'         => 0,
            'maxJobs'         => 0,
            'memory'          => 1280,
            'tries'           => 10,
            'timeout'         => 3600,
            'nice'            => 0,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
        ],
        'analytics'           => [
            'connection'      => 'redis',
            'queue'           => ['analytics'],
            'balance'         => 'auto',
            'maxProcesses'    => 20,
            'maxTime'         => 0,
            'maxJobs'         => 0,
            'memory'          => 1280,
            'tries'           => 2,
            'timeout'         => 120,
            'nice'            => 0,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
        ],
        'low-priority'     => [
            'connection'          => 'redis',
            'queue'               => ['low-priority'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses'        => 1,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 1280,
            'tries'               => 24,
            'timeout'             => 3600,
            'retry_after'         => 600,
            'nice'                => 0,
            'balanceMaxShift'     => 1,
            'balanceCooldown'     => 3,
        ],
        'long-running'     => [
            'connection'          => 'redis-long-running',
            'queue'               => ['default-long', 'ses'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses'        => 3,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 1280,
            'tries'               => 3,
            'timeout'             => 7200,
            'retry_after'         => 600,
            'nice'                => 0,
            'balanceMaxShift'     => 1,
            'balanceCooldown'     => 3,
        ],


    ],

    'environments' => [
        'production' => [
            'normal'           => [
                'maxProcesses' => env('HORIZON_NORMAL_WORKERS', 12),
            ],
            'aurora'           => [
                'maxProcesses' => env('HORIZON_AURORA_WORKERS', 12),
            ],
            'analytics'           => [
                'maxProcesses' => env('HORIZON_ANALYTICS_WORKERS', 12),
            ],
            'sales'            => [
                'maxProcesses' => env('HORIZON_SALES_WORKERS', 12),
            ],
            'universal-search' => [
                'maxProcesses' => env('HORIZON_UNIVERSAL_SEARCH_WORKERS', 16),
            ],
            'urgent'           => [
                'maxProcesses' => env('HORIZON_URGENT_WORKERS', 16),
            ],
            'low-priority'     => [
                'maxProcesses' => env('HORIZON_LOW_PRIORITY_WORKERS', 24),
            ],
            'long-running'     => [
                'maxProcesses' => env('HORIZON_LONG_WORKERS', 16),
            ],

        ],
        'staging'    => [
            'normal'           => [
                'maxProcesses' => env('HORIZON_NORMAL_WORKERS', 2),
            ],
            'aurora'           => [
                'maxProcesses' => env('HORIZON_NORMAL_AURORA', 2),
            ],
            'sales'            => [
                'maxProcesses' => env('HORIZON_SALES_WORKERS', 2),
            ],
            'universal-search' => [
                'maxProcesses' => env('HORIZON_UNIVERSAL_SEARCH_WORKERS', 8),
            ],
            'urgent'           => [
                'maxProcesses' => env('HORIZON_URGENT_WORKERS', 2),
            ],
            'low-priority'     => [
                'maxProcesses' => env('HORIZON_LOW_PRIORITY_WORKERS', 2),
            ],
            'long-running'     => [
                'maxProcesses' => env('HORIZON_LONG_WORKERS', 2),
            ],


        ],
        'local'      => [
            'normal'           => [
                'maxProcesses' => env('HORIZON_NORMAL_WORKERS', 5),
            ],
            'aurora'           => [
                'maxProcesses' => env('HORIZON_NORMAL_AURORA', 2),
            ],
            'sales'            => [
                'maxProcesses' => env('HORIZON_SALES_WORKERS', 5),
            ],
            'universal-search' => [
                'maxProcesses' => env('HORIZON_UNIVERSAL_SEARCH_WORKERS', 5),
            ],
            'urgent'           => [
                'maxProcesses' => env('HORIZON_URGENT_WORKERS', 5),
            ],

            'low-priority' => [
                'maxProcesses' => env('HORIZON_LOW_PRIORITY_WORKERS', 2),
            ],
            'long-running' => [
                'maxProcesses' => env('HORIZON_LONG_WORKERS', 10),
            ],


        ],
    ],
];
