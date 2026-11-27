<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 13:57:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_connections', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->unique(['user_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::table('slack_connections', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'team_id']);
            $table->unique(['user_id']);
        });
    }
};
