<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shopping_assistant_conversations', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('website_id');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_assistant_conversations', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['website_id']);
        });
    }
};
