<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * An instance is known from the App Instance Installed webhook before we can tell which
     * customer installed it, so it has to be storable unlinked.
     */
    public function up(): void
    {
        Schema::table('wix_users', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->change();
            $table->unsignedSmallInteger('group_id')->nullable()->change();
            $table->unsignedSmallInteger('organisation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('wix_users', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable(false)->change();
            $table->unsignedSmallInteger('group_id')->nullable(false)->change();
            $table->unsignedSmallInteger('organisation_id')->nullable(false)->change();
        });
    }
};
