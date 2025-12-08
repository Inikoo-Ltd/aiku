<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Feb 2025 10:22:25 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Fulfilment\PalletStoredItem\PalletStoredItemStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_stored_items', function (Blueprint $table) {
            $table->string('state')->default(PalletStoredItemStateEnum::IN_PROCESS)->index();
        });
    }

    public function down(): void
    {
        Schema::table('pallet_stored_items', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
