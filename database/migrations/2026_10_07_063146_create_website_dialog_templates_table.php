<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_dialog_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->string('code');
            $table->string('name');
            $table->string('component');
            $table->unsignedSmallInteger('position')->default(0);
            $table->jsonb('data')->default('{}');
            $table->timestampsTz();
            $table->unique(['group_id', 'code']);
        });

        Schema::table('website_dialogs', function (Blueprint $table) {
            $table->string('component')->nullable()->after('template_code');
        });
    }

    public function down(): void
    {
        Schema::table('website_dialogs', function (Blueprint $table) {
            $table->dropColumn('component');
        });

        Schema::dropIfExists('website_dialog_templates');
    }
};
