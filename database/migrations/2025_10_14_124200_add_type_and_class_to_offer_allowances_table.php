<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Oct 2025 12:43:45 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('offer_allowances', function (Blueprint $table) {
            if (!Schema::hasColumn('offer_allowances', 'class')) {
                $table->string('class')->nullable()->index()->comment('For analytics');
            }
            if (!Schema::hasColumn('offer_allowances', 'type')) {
                $table->string('type')->nullable()->index();
            }

            if (!Schema::hasColumn('offer_allowances', 'target_id')) {
                $table->unsignedInteger('target_id')->nullable()->index();
            }

            if (!Schema::hasColumn('offer_allowances', 'target_data')) {
                $table->jsonb('target_data')->nullable()->comment('For complex target policies');
            }

        });
    }

    public function down(): void
    {
        Schema::table('offer_allowances', function (Blueprint $table) {
            if (Schema::hasColumn('offer_allowances', 'type')) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('offer_allowances', 'class')) {
                $table->dropIndex(['class']);
                $table->dropColumn('class');
            }
            if (Schema::hasColumn('offer_allowances', 'target_id')) {
                $table->dropIndex(['target_id']);
                $table->dropColumn('target_id');
            }
            if (Schema::hasColumn('offer_allowances', 'target_data')) {
                $table->dropColumn('target_data');
            }
        });
    }
};
