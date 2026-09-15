<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 19:36:03 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

//test('globals')
//    ->expect(['dd', 'dump'])
//    ->not->toBeUsedIn([
//
//        'App\Actions',
//        'App\Adapter',
//        'App\Concerns',
//        'App\Console',
//        'App\Enums',
//        'App\Events',
//        'App\Exceptions',
//        'App\Exports',
//        'App\Helpers',
//        'App\Http',
//        'App\Imports',
//        'App\InertiaTable',
//        'App\Models',
//        'App\Notifications',
//        'App\Rules',
//        'App\Services',
//        'App\Stubs',
//    ]);

test('feature test files that write to the database restore it first, so they never inherit rows from the file that ran before them in the same worker', function () {
    $writesToDatabase = '/factory\(|::create\(|createShop|createGroup|createOrganisation|->save\(|::make\(\)->action/';

    $missing = collect(\Illuminate\Support\Facades\File::allFiles(base_path('tests/Feature')))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), 'Test.php'))
        ->filter(fn ($file) => preg_match($writesToDatabase, $file->getContents()) && !str_contains($file->getContents(), 'loadDB()'))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
