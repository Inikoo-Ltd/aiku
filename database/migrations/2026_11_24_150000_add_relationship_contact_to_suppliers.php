<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 15:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedSmallInteger('relationship_contact_user_id')->nullable()->index()->comment('Staff member who owns the relationship with the supplier, labelled Main on the supplier portal');
            $table->foreign('relationship_contact_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['relationship_contact_user_id']);
            $table->dropColumn('relationship_contact_user_id');
        });
    }
};
