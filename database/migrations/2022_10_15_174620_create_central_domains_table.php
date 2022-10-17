<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Sat, 15 Oct 2022 20:59:52 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        Schema::create('central_domains', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('tenant_id')->constrained();
            $table->unsignedMediumInteger('website_id')->index();
            $table->string('domain')->unique();
            $table->enum('state',['created','iris-enabled'])->default('created');

            $table->timestampsTz();
        });
    }


    public function down()
    {
        Schema::dropIfExists('central_domains');
    }
};
