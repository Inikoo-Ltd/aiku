<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_product_categories', function (Blueprint $table): void {
            if (!Schema::hasColumn('master_product_categories', 'extra_desc_art2')) {
                $table->unsignedInteger('extra_desc_art2')->nullable();
            }

            if (!Schema::hasColumn('master_product_categories', 'extra_desc_art3')) {
                $table->unsignedInteger('extra_desc_art3')->nullable();
            }

            if (!Schema::hasColumn('master_product_categories', 'extra_desc_art4')) {
                $table->unsignedInteger('extra_desc_art4')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_product_categories', function (Blueprint $table): void {
            $columnsToDrop = [];

            if (Schema::hasColumn('master_product_categories', 'extra_desc_art2')) {
                $columnsToDrop[] = 'extra_desc_art2';
            }

            if (Schema::hasColumn('master_product_categories', 'extra_desc_art3')) {
                $columnsToDrop[] = 'extra_desc_art3';
            }

            if (Schema::hasColumn('master_product_categories', 'extra_desc_art4')) {
                $columnsToDrop[] = 'extra_desc_art4';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
