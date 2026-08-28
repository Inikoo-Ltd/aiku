<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A draft only exists in Aiku, so it has no Meta identifier until it is submitted.
     * Postgres allows repeated nulls under a unique index, so drafts do not collide.
     */
    public function up(): void
    {
        Schema::table('meta_message_templates', function (Blueprint $table) {
            $table->string('template_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('meta_message_templates', function (Blueprint $table) {
            $table->string('template_id')->nullable(false)->change();
        });
    }
};
