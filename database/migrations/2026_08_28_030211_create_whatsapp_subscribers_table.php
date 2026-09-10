<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('whatsapp_subscribers', function (Blueprint $table) {
            $table->increments('id');
            $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade');

            $table->string('opt_in_method')->index();

            $table->string('parent_type')->nullable()->index()->comment('Customer|MetaChatSession');
            $table->unsignedInteger('parent_id')->nullable();

            $table->timestampsTz();
            $this->softDeletes($table);

            $table->index(['parent_type', 'parent_id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('whatsapp_subscribers');
    }
};
