<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('back_in_stock_reminders', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');

            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedInteger('product_id')->index();
            $table->foreign('product_id')->references('id')->on('products');
            $table->unsignedInteger('family_id')->index()->nullable();
            $table->foreign('family_id')->references('id')->on('product_categories');
            $table->unsignedInteger('sub_department_id')->index()->nullable();
            $table->foreign('sub_department_id')->references('id')->on('product_categories');
            $table->unsignedInteger('department_id')->index()->nullable();
            $table->foreign('department_id')->references('id')->on('product_categories');

            $table->timestampsTz();
            $table->dateTimeTz('un_reminded_at')->nullable()->index();
            $table->unsignedBigInteger('current_reminder_id')->index()->nullable();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
        });

        Schema::table('back_in_stock_reminders', function (Blueprint $table) {
            $table->foreign('current_reminder_id')->references('id')->on('back_in_stock_reminders');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('back_in_stock_reminders');
    }
};
