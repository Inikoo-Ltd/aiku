<?php

use App\Enums\Production\RawMaterial\RawMaterialTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('location_raw_materials', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('raw_material_id')->index();
            $table->foreign('raw_material_id')->references('id')->on('raw_materials');
            $table->unsignedInteger('location_id')->index();
            $table->foreign('location_id')->references('id')->on('locations');
            $table->decimal('quantity', 16, 3)->default(0)->comment('in units');
            $table->string('type')->index()->default(RawMaterialTypeEnum::STOCK->value);
            $table->smallInteger('picking_priority')->nullable()->index();
            $table->string('notes')->nullable();
            $table->jsonb('data');
            $table->jsonb('settings');
            $table->dateTimeTz('audited_at')->nullable()->index();
            $table->timestampsTz();
            $table->unsignedInteger('source_raw_material_id')->nullable();
            $table->unsignedInteger('source_location_id')->nullable();
            $table->boolean('dropshipping_pipe')->default(false)->index();
            $table->unique(['raw_material_id', 'location_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('location_raw_materials');
    }
};
