<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Aug 2026 09:00:02 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->string('activity_type')->default(ManufactureTaskSessionActivityTypeEnum::PRODUCTION->value)->index();
            $table->string('non_productive_reason')->nullable();
            $table->decimal('hours', 10, 4)->nullable();
            $table->decimal('units_per_hour', 10, 2)->nullable();
            $table->unsignedSmallInteger('pay_band_id')->nullable();
            $table->foreign('pay_band_id')->references('id')->on('manufacture_pay_bands');
            $table->string('band_code')->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->decimal('pay', 10, 2)->nullable();
            $table->decimal('bonus', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->dropForeign(['pay_band_id']);
            $table->dropColumn([
                'break_minutes',
                'activity_type',
                'non_productive_reason',
                'hours',
                'units_per_hour',
                'pay_band_id',
                'band_code',
                'hourly_rate',
                'pay',
                'bonus',
            ]);
        });
    }
};
