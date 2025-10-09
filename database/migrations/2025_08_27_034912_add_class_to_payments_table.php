<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Aug 2025 22:32:51 Central Standard Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Accounting\Payment\PaymentClassEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('class')->default(PaymentClassEnum::ORDER->value);
        });
    }


    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('class');
        });
    }
};
