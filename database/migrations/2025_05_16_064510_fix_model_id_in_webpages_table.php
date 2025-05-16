<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 14:47:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE webpages ALTER COLUMN model_id TYPE INTEGER USING (model_id::integer)');

        Schema::table('webpages', function (Blueprint $table) {
            $table->unsignedInteger('model_id')->index()->nullable()->change();

        });
    }


    public function down(): void
    {
        // Convert back to the original type (presumably VARCHAR)
        DB::statement('ALTER TABLE webpages ALTER COLUMN model_id TYPE VARCHAR USING (model_id::VARCHAR)');

        Schema::table('webpages', function (Blueprint $table) {
            $table->string('model_id')->nullable()->change();
        });
    }
};
