<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('web_block_has_external_link', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);

            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites');

            $table->unsignedInteger('webpage_id')->index();
            $table->foreign('webpage_id')->references('id')->on('webpages');

            $table->unsignedInteger('web_block_id')->index();
            $table->foreign('web_block_id')->references('id')->on('web_blocks');

            $table->unsignedInteger('external_link_id')->index();
            $table->foreign('external_link_id')->references('id')->on('external_links');

            $table->boolean('show');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_block_has_external_link');
    }
};
