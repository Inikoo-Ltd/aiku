<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 May 2025 11:38:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Ordering\Order\OrderChargesEngineEnum;
use App\Enums\Ordering\Order\OrderShippingEngineEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('nest');
            $table->string('shipping_engine')->index()->default(OrderShippingEngineEnum::AUTO->value);
            $table->string('charges_engine')->index()->default(OrderChargesEngineEnum::AUTO->value);
        });
    }


    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_engine');
            $table->dropColumn('charges_engine');
            $table->string('nest');
        });
    }
};
