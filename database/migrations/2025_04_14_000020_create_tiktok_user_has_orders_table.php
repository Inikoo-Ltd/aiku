<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 14:33:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Dropshipping\ChannelFulfilmentStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tiktok_user_has_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('tiktok_user_id');
            $table->foreign('tiktok_user_id')->references('id')->on('tiktok_users')->onDelete('cascade');

            $table->morphs('orderable');
            $table->string('state')->default(ChannelFulfilmentStateEnum::OPEN->value);

            $table->unsignedBigInteger('tiktok_fulfilment_id')->nullable();
            $table->unsignedBigInteger('tiktok_order_id')->nullable();

            $table->unsignedBigInteger('customer_client_id');
            $table->foreign('customer_client_id')->references('id')->on('customer_clients')->onDelete('cascade');

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('tiktok_user_has_orders');
    }
};
