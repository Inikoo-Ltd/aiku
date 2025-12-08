<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Nov 2025 16:42:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offers')) {
            Schema::table('offers', function (Blueprint $table) {
                if (! Schema::hasColumn('offers', 'duration')) {
                    $table->string('duration')->default('interval')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('offer_allowances')) {
            Schema::table('offer_allowances', function (Blueprint $table) {
                if (! Schema::hasColumn('offer_allowances', 'duration')) {
                    $table->string('duration')->default('interval')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('offers')) {
            Schema::table('offers', function (Blueprint $table) {
                if (Schema::hasColumn('offers', 'duration')) {
                    // Drop index if named implicitly; Laravel will handle it by column when possible
                    $table->dropIndex(['duration']);
                    $table->dropColumn('duration');
                }
            });
        }

        if (Schema::hasTable('offer_allowances')) {
            Schema::table('offer_allowances', function (Blueprint $table) {
                if (Schema::hasColumn('offer_allowances', 'duration')) {
                    $table->dropIndex(['duration']);
                    $table->dropColumn('duration');
                }
            });
        }
    }
};
