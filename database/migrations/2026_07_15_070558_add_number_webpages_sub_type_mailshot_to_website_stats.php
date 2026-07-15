<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Wed Jul 15 2026
 * Copyright (c) 2026, Eka Yudinata
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('website_stats', function (Blueprint $table) {
            if (!Schema::hasColumn('website_stats', 'number_webpages_sub_type_mailshot')) {
                $table->unsignedSmallInteger('number_webpages_sub_type_mailshot')->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('website_stats', function (Blueprint $table) {
            if (Schema::hasColumn('website_stats', 'number_webpages_sub_type_mailshot')) {
                $table->dropColumn('number_webpages_sub_type_mailshot');
            }
        });
    }
};
