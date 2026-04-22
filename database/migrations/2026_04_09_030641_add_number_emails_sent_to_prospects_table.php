<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 9 Apr 2026 11:06:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('prospects', function (Blueprint $table) {
            if (!Schema::hasColumn('prospects', 'number_dispatched_emails')) {
                $table->unsignedInteger('number_dispatched_emails')->default(0)->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('prospects', function (Blueprint $table) {
            if (Schema::hasColumn('prospects', 'number_dispatched_emails')) {
                try {
                    $table->dropIndex('prospects_number_dispatched_emails_index');
                } catch (\Throwable) {
                    // ignore if the index does not exist
                }
                $table->dropColumn('number_dispatched_emails');
            }
        });
    }
};
