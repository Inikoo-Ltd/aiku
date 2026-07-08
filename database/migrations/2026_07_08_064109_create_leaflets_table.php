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
        Schema::create('leaflets', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('packaging_id')->nullable()->index();
            $table->foreign('packaging_id')->references('id')->on('packagings');
            $table->string('name');
            $table->string('type', 64)->index()->comment('Values: '.implode(', ', LeafletTypeEnum::values()));
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->string('state', 32)->index()->default(LeafletStateEnum::ACTIVE->value)->comment('Values: '.implode(', ', LeafletStateEnum::values()));
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaflets');
    }
};
