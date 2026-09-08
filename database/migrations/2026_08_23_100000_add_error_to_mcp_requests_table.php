<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('mcp_requests', function (Blueprint $table) {
            $table->text('error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mcp_requests', function (Blueprint $table) {
            $table->dropColumn('error');
        });
    }
};
