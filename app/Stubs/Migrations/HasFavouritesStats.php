<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Oct 2024 12:35:00 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasFavouritesStats
{
    public function getFavouritesStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_favourites')->default(0);
        $table->unsignedSmallInteger('number_unfavourited')->default(0);

        return $table;
    }

    public function getCustomersWhoFavouritedStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_customers_who_favourited')->default(0);
        $table->unsignedSmallInteger('number_customers_who_un_favourited')->default(0);

        return $table;
    }
}
