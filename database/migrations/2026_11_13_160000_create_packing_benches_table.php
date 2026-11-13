<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 17:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('packing_benches', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->boolean('status')->index()->default(true);
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code', 64)->index()->collation('und_ns');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->unsignedInteger('packing_bench_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('packing_bench_id');
        });
        Schema::dropIfExists('packing_benches');
    }
};
