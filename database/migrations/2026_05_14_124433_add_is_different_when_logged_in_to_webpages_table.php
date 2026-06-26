<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 May 2026 20:47:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->boolean('is_different_when_logged_in')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropColumn('is_different_when_logged_in');
        });
    }
};
