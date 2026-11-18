<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 12:51:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ticket_projects', function (Blueprint $table) {
            $table->unsignedInteger('parent_id')->nullable()->index()->after('group_id');
            $table->foreign('parent_id')->references('id')->on('ticket_projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_projects', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
