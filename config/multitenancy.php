<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Mar 2023 22:03:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Models\Central\Tenant;
use App\Resolver\TenantResolver;
use App\Tasks\SetupFilesystemsTask;
use App\Tasks\SwitchTenantDatabaseSchemaTask;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Actions\MigrateTenantAction;
use Spatie\Multitenancy\Tasks\PrefixCacheTask;

return [

    'landlord_database_connection_name ' => 'central',

    /*
     * This class is responsible for determining which tenant should be current
     * for the given request.
     *
     * This class should extend `Spatie\Multitenancy\TenantFinder\TenantFinder`
     *
     */
    'tenant_finder'                      => TenantResolver::class,

    /*
     * These fields are used by tenant:artisan command to match one or more tenant
     */
    'tenant_artisan_search_fields'       => [
        'slug',
    ],

    /*
     * These tasks will be performed when switching tenants.
     *
     * A valid task is any class that implements Spatie\Multitenancy\Tasks\SwitchTenantTask
     */
    'switch_tenant_tasks'                => [
        SwitchTenantDatabaseSchemaTask::class,
        SetupFilesystemsTask::class,
        PrefixCacheTask::class,
        // \Spatie\Multitenancy\Tasks\SwitchRouteCacheTask::class,
    ],

    /*
     * This class is the model used for storing configuration on tenants.
     *
     * It must be or extend `Spatie\Multitenancy\Models\Tenant::class`
     */
    'tenant_model'                       => Tenant::class,

    /*
     * If there is a current tenant when dispatching a job, the id of the current tenant
     * will be automatically set on the job. When the job is executed, the set
     * tenant on the job will be made current.
     */
    'queues_are_tenant_aware_by_default' => true,

    /*
     * The connection name to reach the tenant database.
     *
     * Set to `null` to use the default connection.
     */
    'tenant_database_connection_name'    => 'tenant',

    /*
     * The connection name to reach the landlord database
     */
    'landlord_database_connection_name'  => 'central',

    /*
     * This key will be used to bind the current tenant in the container.
     */
    'current_tenant_container_key'       => 'currentTenant',

    /**
     * Set it to `true` if you like to cache the tenant(s) routes
     * in a shared file using the `SwitchRouteCacheTask`.
     */
    'shared_routes_cache'                => false,

    /*
     * You can customize some behavior of this package by using our own custom action.
     * Your custom action should always extend the default one.
     */
    'actions'                            => [
        'make_tenant_current_action'     => MakeTenantCurrentAction::class,
        'forget_current_tenant_action'   => ForgetCurrentTenantAction::class,
        'make_queue_tenant_aware_action' => MakeQueueTenantAwareAction::class,
        'migrate_tenant'                 => MigrateTenantAction::class,
    ],

    /*
     * You can customize the way in which the package resolves the queryable to a job.
     *
     * For example, using the package laravel-actions (by Loris L), you can
     * resolve JobDecorator to getAction() like so: JobDecorator::class => 'getAction'
     */
    'queueable_to_job'                   => [
        SendQueuedMailable::class      => 'mailable',
        SendQueuedNotifications::class => 'notification',
        CallQueuedListener::class      => 'class',
        BroadcastEvent::class          => 'event',
    ],

    /*
     * Jobs tenant aware even if these don't implement the TenantAware interface.
     */
    'tenant_aware_jobs'                  => [
        // ...
    ],

    /*
     * Jobs not tenant aware even if these don't implement the NotTenantAware interface.
     */
    'not_tenant_aware_jobs'              => [
        // ...
    ],
];
