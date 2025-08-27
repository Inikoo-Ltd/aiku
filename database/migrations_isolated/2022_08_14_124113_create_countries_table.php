<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sun, 14 Aug 2022 20:42:47 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 2)->unique()->collation('und_ci');
            $table->string('iso3', 3)->nullable()->index()->collation('und_ci');
            $table->string('phone_code')->nullable()->collation('und_ns');
            $table->string('name')->collation('und_ci');
            $table->string('continent')->collation('und_ci')->nullable();
            $table->string('capital')->nullable()->collation('und_ci');
            $table->unsignedSmallInteger('timezone_id')->nullable()->comment('Timezone in capital')->index();
            $table->unsignedSmallInteger('currency_id')->nullable()->index();
            $table->string('type')->nullable()->index()->default('independent');
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
