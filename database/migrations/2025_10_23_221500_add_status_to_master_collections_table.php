<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Oct 2025 22:17:11 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_collections', function (Blueprint $table) {
            if (! Schema::hasColumn('master_collections', 'status')) {
                $table->boolean('status')->default(true)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_collections', function (Blueprint $table) {
            if (Schema::hasColumn('master_collections', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }
        });
    }
};
