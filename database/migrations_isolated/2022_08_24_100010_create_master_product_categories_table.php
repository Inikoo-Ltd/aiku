<?php

use App\Stubs\Migrations\HasAssetCodeDescription;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasAssetCodeDescription;
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('master_product_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('master_shop_id')->index();
            $table->foreign('master_shop_id')->references('id')->on('master_shops')->onUpdate('cascade')->onDelete('cascade');
            $table->string('type')->index();
            $table->boolean('status')->index()->default(true);
            $table->unsignedInteger('master_department_id')->nullable()->index();
            $table->unsignedInteger('master_sub_department_id')->nullable()->index();
            $table->unsignedInteger('master_parent_id')->nullable()->index();
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->assertCodeDescription($table);
            $table->unsignedInteger('image_id')->nullable();
            $table->jsonb('data');

            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_department_id')->nullable()->unique();
            $table->string('source_family_id')->nullable()->unique();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('master_product_categories');
    }
};
