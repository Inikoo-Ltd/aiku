<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sun, 14 Aug 2022 20:44:12 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code')->unique()->collation('und_ci');
            $table->string('name')->nullable()->index()->collation('und_ci');
            $table->string('native_name')->nullable()->collation('und_ci');
            $table->string('status')->default(false)->nullable()->index();
            $table->jsonb('data');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
