<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 May 2025 10:22:12 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('parent_type')->index();
            $table->unsignedInteger('parent_id')->index();
            $table->index(['parent_type', 'parent_id']);
        });
    }


    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex(['parent_type']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn(['parent_type', 'parent_id']);
            $table->dropIndex(['parent_type', 'parent_id']);
        });
    }
};
