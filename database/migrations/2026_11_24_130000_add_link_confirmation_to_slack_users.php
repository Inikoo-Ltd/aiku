<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_users', function (Blueprint $table) {
            $table->dateTimeTz('link_confirmed_at')->nullable()->comment('An engineer confirmed user_id by hand, so the link counts although the emails differ');
            $table->foreignId('link_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('slack_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('link_confirmed_by_user_id');
            $table->dropColumn('link_confirmed_at');
        });
    }
};
