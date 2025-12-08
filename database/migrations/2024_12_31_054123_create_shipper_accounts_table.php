<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Dec 2024 19:12:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
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
        Schema::create('shipper_accounts', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index()->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('shipper_id')->index()->nullable();
            $table->foreign('shipper_id')->references('id')->on('shippers')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code')->index();
            $table->boolean('status')->default('true')->index();
            $table->jsonb('data');
            $table->jsonb('settings');
            $table->jsonb('credentials');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipper_accounts');
    }
};
