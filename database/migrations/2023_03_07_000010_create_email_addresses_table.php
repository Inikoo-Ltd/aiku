<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Mar 2023 23:13:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');

            // https://stackoverflow.com/questions/1199190/what-is-the-optimal-length-for-an-email-address-in-a-database
            $table->string('email', 254)->unique();
            $table->dateTimeTz('last_marketing_dispatch_at')->nullable()->index();
            $table->dateTimeTz('last_transactional_dispatch_at')->nullable()->index();
            $table->dateTimeTz('soft_bounced_at')->nullable()->index();
            $table->dateTimeTz('hard_bounced_at')->nullable()->index();
            $table->unsignedSmallInteger('number_marketing_dispatches')->default(0);
            $table->unsignedSmallInteger('number_transactional_dispatches')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_addresses');
    }
};
