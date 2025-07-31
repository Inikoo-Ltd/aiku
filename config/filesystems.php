<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Nov 2023 23:48:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],
        'art' => [
            'driver' => 'local',
            'root'   => resource_path('art'),
            'throw'  => true,
        ],
        'datasets' => [
            'driver' => 'local',
            'root'   => database_path('seeders/datasets'),
            'throw'  => false,
        ],
        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],
        'media'  => [
            'driver' => 'local',
            'root'   => storage_path('media'),
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0644,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0755,
                ],
            ],
            'throw'  => false,
        ],


        'media_test'  => [
            'driver' => 'local',
            'root'   => storage_path('media_test'),
            'throw'  => false,
        ],
        'r2' => [
            'driver'   => 's3',
            'key'      => env('CLOUDFLARE_R2_ACCESS_KEY'),
            'secret'   => env('CLOUDFLARE_R2_SECRET_KEY'),
            'region'   => env('CLOUDFLARE_R2_REGION', 'auto'),
            'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
            'bucket'   => env('CLOUDFLARE_R2_BUCKET_NAME'),
        ],
        'media-r2' => [
            'driver'   => 's3',
            'key'      => env('CLOUDFLARE_R2_ACCESS_KEY'),
            'secret'   => env('CLOUDFLARE_R2_SECRET_KEY'),
            'region'   => env('CLOUDFLARE_R2_REGION', 'auto'),
            'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
            'bucket'   => env('CLOUDFLARE_R2_MEDIA_BUCKET_NAME'),
        ],
        'google' => [
            'driver'       => 'google',
            'clientId'     => env('GOOGLE_DRIVE_CLIENT_ID'),
            'clientSecret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
            'refreshToken' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
            'folder'       => env('GOOGLE_DRIVE_FOLDER'), // without folder is root of drive or team drive
            //'teamDriveId' => env('GOOGLE_DRIVE_TEAM_DRIVE_ID'),
            //'sharedFolderId' => env('GOOGLE_DRIVE_SHARED_FOLDER_ID'),
        ],
        'backups' => [
            'driver' => 'local',
            'root'   => storage_path('backups'),
            'throw'  => false,
        ],
        'sftp' => [
            'driver'   => 'sftp',
            'host'     => env('SFTP_HOST'),
            'username' => env('SFTP_USERNAME'),
            'password' => env('SFTP_PASSWORD'),
            'root'     => storage_path('app/media'),
            'timeout'  => 30,
        ],
        'excel-uploads' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],
        'excel-exports' => [
            'driver' => 'local',
            'root'   => storage_path('app/excel-exports'),
            'throw'  => false,
        ]

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('images') => resource_path('images/'),
    ],

];
