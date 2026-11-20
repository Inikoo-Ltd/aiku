<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 12:47:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('ticket_projects', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_projects', function (Blueprint $table) {
            $table->dropColumn('is_pinned');
        });
    }
};
