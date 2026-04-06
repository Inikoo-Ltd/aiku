<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 Apr 2026 22:55:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->dropIndex(['parent_type']);
            $table->dropIndex(['parent_type', 'parent_id']);
            $table->dropColumn(['parent_type', 'parent_id']);
        });
    }


    public function down(): void
    {
        Schema::table('dispatched_emails', function (Blueprint $table) {
            $table->string('parent_type')->nullable()->index();
            $table->integer('parent_id')->nullable();
            $table->index(['parent_type', 'parent_id']);
        });
    }
};
