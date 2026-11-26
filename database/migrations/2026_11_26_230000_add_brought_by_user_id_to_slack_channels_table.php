<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_channels', function (Blueprint $table) {
            $table->foreignId('brought_by_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('The opted-in member whose own Slack connection first brought this private channel to Verba (INI-564); null when the aiku bot brought it or it was created from Verba');
        });
    }

    public function down(): void
    {
        Schema::table('slack_channels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brought_by_user_id');
        });
    }
};
