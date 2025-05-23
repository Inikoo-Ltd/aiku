<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 May 2025 14:38:08 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->string('state')->default(CustomerSalesChannelStateEnum::CREATED->value)->index()->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('customer_sales_channels', function (Blueprint $table) {
            $table->dropColumn(['state']);
        });
    }
};
