<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 May 2025 21:18:07 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private function addCumulativeFields(Blueprint $table): void
    {
        $table->unsignedInteger('number_try_send_failure')->default(0);
        $table->unsignedInteger('number_try_send_success')->default(0);
        $table->unsignedInteger('number_try_send_total')->default(0);

        $table->unsignedInteger('number_deliveries_failure')->default(0);
        $table->unsignedInteger('number_deliveries_success')->default(0);
        $table->unsignedInteger('number_deliveries_total')->default(0);

        $table->unsignedInteger('number_deliveries_success_open_failure')->default(0);
        $table->unsignedInteger('number_deliveries_success_open_success')->default(0);
    }

    private function dropCumulativeFields(Blueprint $table): void
    {
        $table->dropColumn([
            'number_try_send_failure',
            'number_try_send_success',
            'number_try_send_total',
            'number_deliveries_failure',
            'number_deliveries_success',
            'number_deliveries_total',
            'number_deliveries_success_open_failure',
            'number_deliveries_success_open_success'
        ]);
    }

    public function up(): void
    {
        Schema::table('mailshot_stats', function (Blueprint $table) {
            $this->addCumulativeFields($table);
        });

        Schema::table('email_bulk_run_stats', function (Blueprint $table) {
            $this->addCumulativeFields($table);
        });
    }

    public function down(): void
    {
        Schema::table('mailshot_stats', function (Blueprint $table) {
            $this->dropCumulativeFields($table);
        });

        Schema::table('email_bulk_run_stats', function (Blueprint $table) {
            $this->dropCumulativeFields($table);
        });
    }
};
