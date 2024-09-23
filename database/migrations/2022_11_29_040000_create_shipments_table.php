<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Feb 2023 19:13:13 Malaysia Time, Plane KL- Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index()->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('shipper_id')->index()->nullable();
            $table->foreign('shipper_id')->references('id')->on('shippers');
            $table->unsignedInteger('customer_id')->index()->nullable();
            $table->foreign('customer_id')->references('id')->on('customers');

            $table->string('status')->default('processing')->index();

            $table->string('reference')->nullable();
            $table->string('tracking')->nullable()->index();
            $table->text('error_message')->nullable();

            $table->jsonb('data');
            $table->dateTimeTz('shipped_at')->nullable()->index();
            $table->dateTimeTz('tracked_at')->nullable()->index();
            $table->unsignedSmallInteger('tracked_count')->default(0);

            $table->timestampsTz();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
