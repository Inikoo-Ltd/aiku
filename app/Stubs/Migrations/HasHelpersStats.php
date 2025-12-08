<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 19:27:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasHelpersStats
{
    public function imagesStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_images')->default(0);
        $table->unsignedInteger('filesize_images')->default(0);

        return $table;
    }

    public function attachmentsStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_attachments')->default(0);
        $table->unsignedInteger('filesize_attachments')->default(0);

        return $table;
    }

    public function uploadStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_uploads')->default(0);
        $table->unsignedInteger('number_upload_records')->default(0);

        return $table;
    }
}
