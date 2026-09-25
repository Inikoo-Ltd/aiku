<?php

use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Enums\Catalogue\Packaging\PackagingTypeEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('packagings', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('family_code', 64)->index();
            $table->string('code', 64)->index();
            $table->string('name');
            $table->string('type', 64)->index()->default(PackagingTypeEnum::STANDARD->value)->comment('Values: '.implode(', ', PackagingTypeEnum::values()));
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->unsignedInteger('width')->nullable()->comment('mm');
            $table->unsignedInteger('height')->nullable()->comment('mm');
            $table->unsignedInteger('depth')->nullable()->comment('mm');
            $table->unsignedBigInteger('box_id')->nullable()->index();
            $table->foreign('box_id')->references('id')->on('boxes');
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media');
            $table->string('state', 32)->index()->default(PackagingStateEnum::IN_PROCESS->value)->comment('Values: '.implode(', ', PackagingStateEnum::values()));
            $table->unsignedSmallInteger('position')->default(0);
            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packagings');
    }
};
