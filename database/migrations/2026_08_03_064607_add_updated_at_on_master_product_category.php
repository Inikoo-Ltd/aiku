<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_product_categories', function ($table) {
            $table->datetimeTz('name_updated_at')->nullable();
            $table->datetimeTz('description_updated_at')->nullable();
            $table->datetimeTz('description_title_updated_at')->nullable();
            $table->datetimeTz('extra_description_updated_at')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('master_product_categories', function ($table) {
            $table->dropColumn([
                'name_updated_at',
                'description_updated_at',
                'description_title_updated_at',
                'extra_description_updated_at',
            ]);
        });
    }
};
