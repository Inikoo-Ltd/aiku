<?php

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
        Schema::table('mailshots', function (Blueprint $table) {

            if (!Schema::hasColumn('mailshots', 'is_second_wave_active')) {
                $table->boolean('is_second_wave_active')->default(false);
            }
            if (!Schema::hasColumn('mailshots', 'is_second_wave')) {
                $table->boolean('is_second_wave')->default(false);
            }
            if (!Schema::hasColumn('mailshots', 'parent_mailshot_id')) {
                $table->foreignId('parent_mailshot_id')->nullable()->constrained('mailshots');
            }
            if (!Schema::hasColumn('mailshots', 'send_delay_hours')) {
                $table->unsignedInteger('send_delay_hours')->default(0);
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
        Schema::table('mailshots', function (Blueprint $table) {
            if (Schema::hasColumn('mailshots', 'parent_mailshot_id')) {
                $table->dropForeign(['parent_mailshot_id']);
                $table->dropColumn('parent_mailshot_id');
            }
            if (Schema::hasColumn('mailshots', 'is_second_wave_active')) {
                $table->dropColumn('is_second_wave_active');
            }
            if (Schema::hasColumn('mailshots', 'is_second_wave')) {
                $table->dropColumn('is_second_wave');
            }
            if (Schema::hasColumn('mailshots', 'send_delay_hours')) {
                $table->dropColumn('send_delay_hours');
            }
        });
    }
};
