<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 30 Sep 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('variants', function (Blueprint $table) {
            $table->boolean('is_label_reviewed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('variants', function (Blueprint $table) {
            $table->dropColumn('is_label_reviewed');
        });
    }
};
