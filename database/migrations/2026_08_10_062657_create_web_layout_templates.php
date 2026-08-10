<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('web_layout_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->index();
            $table->string('type')->index();
            $table->string('scope')->index();
            $table->jsonb('blocks')->default('{}');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('web_layout_templates');
    }
};
