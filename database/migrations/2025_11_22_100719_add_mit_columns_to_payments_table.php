<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Nov 2025 18:08:21 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_mit')->index()->nullable()->default(false);
            $table->string('debug_mit_status')->nullable();
            $table->boolean('debug_mit_is_approved')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['is_mit', 'debug_mit_status', 'debug_mit_is_approved']);
        });
    }
};
