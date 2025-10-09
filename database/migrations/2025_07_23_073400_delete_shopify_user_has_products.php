<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:34:07 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('shopify_user_has_products');
    }


    public function down(): void
    {
        Schema::create('shopify_user_has_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopify_user_id')->constrained()->cascadeOnDelete();
            $table->string('platform_product_id');
            $table->timestamps();
        });
    }
};
