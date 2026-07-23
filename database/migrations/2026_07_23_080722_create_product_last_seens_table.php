<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('product_last_seens', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');

            $table->unsignedInteger('webpage_id')->index();
            $table->foreign('webpage_id')->references('id')->on('webpages');

            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->string('cookie_id')->nullable()->index();

            $table->dateTimeTz('last_seen_at')->index();
            $table->timestampsTz();

            $table->unique(['customer_id', 'webpage_id']);
            $table->unique(['cookie_id', 'webpage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_last_seens');
    }
};
