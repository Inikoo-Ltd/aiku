<?php

use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('model_has_leaflets', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('packaging_id')->nullable()->index();
            $table->foreign('packaging_id')->references('id')->on('packagings');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->unsignedInteger('leaflet_id')->index();
            $table->foreign('leaflet_id')->references('id')->on('leaflets');
            $table->string('type', 64)->index()->comment('Values: '.implode(', ', LeafletTypeEnum::values()));
            $table->string('name');
            $table->unsignedInteger('media_id')->nullable();
            $table->foreign('media_id')->references('id')->on('media');
            $table->string('state', 32)->index()->default(LeafletStateEnum::ACTIVE->value)->comment('Values: '.implode(', ', LeafletStateEnum::values()));
            $table->unsignedSmallInteger('number_pages')->nullable();
            $table->string('print_size', 64)->nullable();
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_leaflets');
    }
};
