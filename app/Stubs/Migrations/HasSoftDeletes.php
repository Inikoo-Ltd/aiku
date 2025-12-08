<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Oct 2023 19:15:38 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasSoftDeletes
{
    public function softDeletes(Blueprint $table): Blueprint
    {
        $table->softDeletesTz();
        $table->string('delete_comment')->nullable();

        return $table;
    }
}
