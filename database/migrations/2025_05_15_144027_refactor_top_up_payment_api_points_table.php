<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 22:40:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('top_up_payment_api_points', function (Blueprint $table) {
            $table->dateTimeTz('processed_at')->nullable();
            $table->dropColumn('in_process');
            $table->string('state')->default(TopUpPaymentApiPointStateEnum::IN_PROCESS)->index();
        });
    }


    public function down(): void
    {
        Schema::table('top_up_payment_api_points', function (Blueprint $table) {
            $table->boolean('in_process')->default(false);
            $table->dropColumn('state');
            $table->dropColumn('processed_at');
        });
    }
};
