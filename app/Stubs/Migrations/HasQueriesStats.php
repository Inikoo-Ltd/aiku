<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Dec 2024 05:45:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasQueriesStats
{
    public function getQueriesStats(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_queries')->default(0);
        $table->unsignedSmallInteger('number_static_queries')->default(0)->comment('is_static=true');
        $table->unsignedSmallInteger('number_dynamic_queries')->default(0)->comment('is_static=false');

        return $table;
    }
}
