<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Mar 2023 22:13:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'aiku'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [



        'aiku' => [
            'driver'         => 'pgsql',
            'url'            => env('DATABASE_URL'),
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '5432'),
            'database'       => env('DB_DATABASE'),
            'username'       => env('DB_USERNAME'),
            'password'       => env('DB_PASSWORD'),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
            'search_path'    => env('DB_SEARCH_PATH', 'public'),
            'sslmode'        => 'prefer',
        ],


        'aurora' => [
            'driver'         => 'mysql',
            'host'           => env('AURORA_DB_HOST', '127.0.0.1'),
            'port'           => env('AURORA_DB_PORT', 3306),
            'database'       => env('AURORA_DB_DATABASE'),
            'username'       => env('AURORA_DB_USERNAME', ''),
            'password'       => env('AURORA_DB_PASSWORD', ''),
            'unix_socket'    => '',
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => null,
            'options'        => extension_loaded('pdo_mysql')
                ? array_filter([
                                   PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                               ]) : [],
        ],
        'wowsbar' => [
            'driver'         => 'pgsql',
            'url'            => null,
            'host'           => env('WOWSBAR_DB_HOST', '127.0.0.1'),
            'port'           => env('WOWSBAR_DB_PORT', '5432'),
            'database'       => env('WOWSBAR_DB_DATABASE'),
            'username'       => env('WOWSBAR_DB_USERNAME'),
            'password'       => env('WOWSBAR_DB_PASSWORD'),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
            'search_path'    => env('WOWSBAR_DB_SEARCH_PATH', 'public'),
            'sslmode'        => 'prefer',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix'  => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'aiku'), '_').'_'.env('APP_ENV').'_db_'),
        ],

        'default' => [
            'url'      => env('REDIS_HORIZON_URL'),
            'host'     => env('REDIS_HORIZON_HOST', '127.0.0.1'),
            'username' => env('REDIS_HORIZON_USERNAME'),
            'password' => env('REDIS_HORIZON_PASSWORD'),
            'port'     => env('REDIS_HORIZON_PORT', '6379'),
            'database' => env('REDIS_HORIZON_DB', '0'),
        ],

        'cache' => [
            'url'      => env('REDIS_CACHE_URL'),
            'host'     => env('REDIS_CACHE_HOST', '127.0.0.1'),
            'username' => env('REDIS_CACHE_USERNAME'),
            'password' => env('REDIS_CACHE_PASSWORD'),
            'port'     => env('REDIS_CACHE_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
