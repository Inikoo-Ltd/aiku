<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Feb 2025 16:03:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stored_items', function (Blueprint $table) {
            $table->string('name')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('stored_items', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
