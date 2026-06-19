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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('identity_document_number_alt', 255)->nullable()->index();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('identity_document_number_alt', 255)->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['identity_document_number_alt']);
        });

        
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['identity_document_number_alt']);
        });
    }
};
