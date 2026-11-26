<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 21:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->string('calculated_band_code')->nullable();
            $table->string('band_override_code')->nullable();
            $table->string('band_override_reason')->nullable();
            $table->text('band_override_note')->nullable();
            $table->unsignedInteger('band_overridden_by')->nullable();
            $table->timestampTz('band_overridden_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_task_sessions', function (Blueprint $table) {
            $table->dropColumn(['calculated_band_code', 'band_override_code', 'band_override_reason', 'band_override_note', 'band_overridden_by', 'band_overridden_at']);
        });
    }
};
