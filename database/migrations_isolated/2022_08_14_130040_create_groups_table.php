<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Nov 2023 23:03:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasAssets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasAssets;
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->ulid()->index();
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('subdomain')->nullable()->unique();
            $table->string('code');
            $table->string('name');
            $table = $this->assets($table);
            $table->jsonb('limits');
            $table->jsonb('data');
            $table->jsonb('settings');
            $table->smallInteger('number_organisations')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
