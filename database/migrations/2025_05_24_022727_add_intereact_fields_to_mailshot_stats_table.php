<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 May 2025 10:27:53 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private function addInteractFields(Blueprint $table): void
    {
        $table->unsignedInteger('number_opened_interact_failure')->default(0);
        $table->unsignedInteger('number_opened_interact_success')->default(0);
        $table->renameColumn('number_deliveries_success_open_failure', 'number_delivered_open_failure');
        $table->renameColumn('number_deliveries_success_open_success', 'number_delivered_open_success');
        $table->dropColumn('number_deliveries_total');
    }

    private function revertInteractFields(Blueprint $table): void
    {
        $table->dropColumn([
            'number_opened_interact_failure',
            'number_opened_interact_success'
        ]);
        $table->renameColumn('number_delivered_open_failure', 'number_deliveries_success_open_failure');
        $table->renameColumn('number_delivered_open_success', 'number_deliveries_success_open_success');
        $table->unsignedInteger('number_deliveries_total')->default(0);
    }

    public function up(): void
    {
        Schema::table('mailshot_stats', function (Blueprint $table) {
            $this->addInteractFields($table);
        });

        Schema::table('email_bulk_run_stats', function (Blueprint $table) {
            $this->addInteractFields($table);
        });
    }

    public function down(): void
    {
        Schema::table('mailshot_stats', function (Blueprint $table) {
            $this->revertInteractFields($table);
        });

        Schema::table('email_bulk_run_stats', function (Blueprint $table) {
            $this->revertInteractFields($table);
        });
    }
};
