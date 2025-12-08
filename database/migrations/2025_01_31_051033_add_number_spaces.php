<?php

use App\Stubs\Migrations\HasSpaceStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasSpaceStats;

    public function up(): void
    {
        Schema::table('fulfilment_customers', function (Blueprint $table) {
            $this->getSpaceFields($table);
        });
        Schema::table('fulfilment_stats', function (Blueprint $table) {
            $this->getSpaceFields($table);
        });
        Schema::table('group_fulfilment_stats', function (Blueprint $table) {
            $this->getSpaceFields($table);
        });
        Schema::table('organisation_fulfilment_stats', function (Blueprint $table) {
            $this->getSpaceFields($table);
        });
    }

    public function down(): void
    {
        Schema::table('fulfilment_customers', function (Blueprint $table) {
            $this->dropSpaceFields($table);
        });
        Schema::table('fulfilment_stats', function (Blueprint $table) {
            $this->dropSpaceFields($table);
        });
        Schema::table('group_fulfilment_stats', function (Blueprint $table) {
            $this->dropSpaceFields($table);
        });
        Schema::table('organisation_fulfilment_stats', function (Blueprint $table) {
            $this->dropSpaceFields($table);
        });
    }
};
