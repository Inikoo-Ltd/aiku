<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Sep 2023 19:05:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index()->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('model')->index();
            $table->string('original_filename');
            $table->string('filename');
            $table->unsignedBigInteger('filesize')->default(0);
            $table->string('path')->nullable();
            $table->unsignedInteger('number_rows')->default(0);
            $table->unsignedInteger('number_success')->default(0);
            $table->unsignedInteger('number_fails')->default(0);
            $table->dateTimeTz('uploaded_at')->nullable()->comment('Date the file was finished store/update actions');
            $table->timestamps();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
