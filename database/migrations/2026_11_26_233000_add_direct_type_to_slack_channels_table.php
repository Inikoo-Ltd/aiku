<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_channels', function (Blueprint $table) {
            $table->string('direct_type', 8)->nullable()->index()->comment('im or mpim: a Slack direct message or group DM brought by a person who opted in (INI-431); readable by its members only');
        });
    }

    public function down(): void
    {
        Schema::table('slack_channels', function (Blueprint $table) {
            $table->dropColumn('direct_type');
        });
    }
};
