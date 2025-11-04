<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('announcement_templates', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();

            $table->string('code')->unique()->index();
            $table->unsignedInteger('screenshot_id')->nullable();

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('announcement_templates');
    }
};
