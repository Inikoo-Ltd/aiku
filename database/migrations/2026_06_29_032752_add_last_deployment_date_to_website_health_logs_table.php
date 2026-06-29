<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('website_health_logs', function (Blueprint $table) {
            $table->timestamp('last_deployment_date')->nullable()->after('error_message');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('website_health_logs', function (Blueprint $table) {
            $table->dropColumn('last_deployment_date');
        });
    }
};
