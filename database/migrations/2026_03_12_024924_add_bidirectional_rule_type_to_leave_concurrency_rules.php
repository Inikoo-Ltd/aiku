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
        Schema::table('leave_concurrency_rules', function (Blueprint $table) {
            $table->comment('rule_type enum: quota, dependency, bidirectional');
        });
    }

    public function down()
    {
        Schema::table('leave_concurrency_rules', function (Blueprint $table) {
            $table->comment('rule_type enum: quota, dependency');
        });
    }
};
