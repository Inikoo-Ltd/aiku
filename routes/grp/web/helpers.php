<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 07 Mar 2023 11:12:51 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

use App\Actions\Helpers\Upload\DownloadUploads;
use App\Actions\Helpers\Upload\UI\ShowUpload;
use App\Actions\SysAdmin\Group\Seeders\SeedWebBlockTypes;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\Route;

Route::prefix('uploads/{upload}')->as('uploads.')->group(function () {
    Route::get('records', ShowUpload::class)->name('records.show');
    Route::get('download', DownloadUploads::class)->name('records.download');
});

\Illuminate\Support\Facades\Route::get('seed-web-block', function (\Illuminate\Http\Request $request) {
    if ($request->get('code') == 'AW95') {
        foreach (Group::all() as $group) {
            SeedWebBlockTypes::run($group);

            print_r("Seeding web block types for group: $group->name");
        }

        return "OK";
    }

    abort(404);
});
