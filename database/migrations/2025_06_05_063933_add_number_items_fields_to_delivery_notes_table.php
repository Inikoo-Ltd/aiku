<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Jun 2025 15:37:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_items')->default(0)->comment('current number of items');
            foreach (DeliveryNoteItemStateEnum::cases() as $case) {
                $table->unsignedInteger("number_items_state_{$case->snake()}")->default(0);
            }

        });
    }


    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('number_items');
            foreach (DeliveryNoteItemStateEnum::cases() as $case) {
                $table->dropColumn("number_items_state_{$case->snake()}");
            }
        });
    }
};
