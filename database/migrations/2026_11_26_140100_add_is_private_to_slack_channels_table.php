<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_channels', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->comment('A Slack private channel brought in by the aiku bot being in it (INI-431); readable by its members only');
        });
    }

    public function down(): void
    {
        Schema::table('slack_channels', function (Blueprint $table) {
            $table->dropColumn('is_private');
        });
    }
};
