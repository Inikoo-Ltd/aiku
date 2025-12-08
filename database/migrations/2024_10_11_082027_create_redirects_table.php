<?php

use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites');
            $table->string('type')->default(RedirectTypeEnum::PERMANENT->value);
            $table->string('redirection')->index()->comment('full url including https');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
