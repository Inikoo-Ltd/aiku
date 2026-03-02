<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('offer_campaigns', function (Blueprint $table) {
            $table->renameColumn('state', 'offers_state');
        });
    }


    public function down(): void
    {
        Schema::table('offer_campaigns', function (Blueprint $table) {
            $table->renameColumn('offers_state', 'state');
        });
    }
};
