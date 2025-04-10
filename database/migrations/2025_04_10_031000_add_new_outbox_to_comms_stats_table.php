<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Apr 2025 16:28:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('group_comms_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_DELIVERY_DELETED->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_RETURN_DELETED->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_DELETED->snake())->default(0);
        });
        Schema::table('organisation_comms_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_DELIVERY_DELETED->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_RETURN_DELETED->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_DELETED->snake())->default(0);
        });
        Schema::table('shop_comms_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_DELIVERY_DELETED->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_RETURN_DELETED->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_DELETED->snake())->default(0);
        });
        Schema::table('post_room_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_DELIVERY_DELETED->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_RETURN_DELETED->snake())->default(0);
            $table->unsignedInteger('number_outboxes_type_'.OutboxCodeEnum::PALLET_DELETED->snake())->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('group_comms_stats', function (Blueprint $table) {
            $table->dropColumn([
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELIVERY_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_RETURN_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELETED->snake(),
            ]);
        });
        Schema::table('organisation_comms_stats', function (Blueprint $table) {
            $table->dropColumn([
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELIVERY_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_RETURN_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELETED->snake(),
            ]);
        });
        Schema::table('shop_comms_stats', function (Blueprint $table) {
            $table->dropColumn([
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELIVERY_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_RETURN_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELETED->snake(),
            ]);
        });
        Schema::table('post_room_stats', function (Blueprint $table) {
            $table->dropColumn([
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_RETURN_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::NEW_PALLET_DELIVERY_FROM_CUSTOMER->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELIVERY_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_RETURN_DELETED->snake(),
            'number_outboxes_type_'.OutboxCodeEnum::PALLET_DELETED->snake(),
            ]);
        });
    }
};
