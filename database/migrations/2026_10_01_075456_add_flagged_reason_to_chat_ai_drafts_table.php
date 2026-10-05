<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 15:55:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->text('flagged_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_ai_drafts', function (Blueprint $table) {
            $table->dropColumn('flagged_reason');
        });
    }
};
