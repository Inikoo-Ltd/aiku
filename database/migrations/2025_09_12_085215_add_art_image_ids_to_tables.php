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
        $tables = ['master_assets', 'trade_units', 'products'];
        
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                for ($i = 1; $i <= 5; $i++) {
                    $columnName = "art{$i}_image_id";
                    $table->unsignedInteger($columnName)->nullable()->index();
                    $table->foreign($columnName)->references('id')->on('media')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tables = ['master_assets', 'trade_units', 'products'];
        
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                for ($i = 1; $i <= 5; $i++) {
                    $columnName = "art{$i}_image_id";
                    $table->dropForeign([$columnName]);
                    $table->dropColumn($columnName);
                }
            });
        }
    }
};
