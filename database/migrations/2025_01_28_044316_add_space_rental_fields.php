<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Jan 2025 14:00:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Billables\Rental\RentalTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfilment_customers', function (Blueprint $table) {
            $table->boolean('space_rental')->default('false')->index()->comment('For customer renting spaces, e.g. storage, parking');
        });
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('type')->index()->default(RentalTypeEnum::STORAGE->value);
        });
    }

    public function down(): void
    {

        Schema::table('fulfilment_customers', function (Blueprint $table) {
            $table->dropColumn('space_rental');
        });

        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('type');
        });

    }
};
