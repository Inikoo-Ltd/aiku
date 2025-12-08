<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Sept 2025 00:44:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_copies', function (Blueprint $table) {
            $table->boolean('is_body_encoded')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('email_copies', function (Blueprint $table) {
            $table->dropColumn('is_body_encoded');
        });
    }
};
