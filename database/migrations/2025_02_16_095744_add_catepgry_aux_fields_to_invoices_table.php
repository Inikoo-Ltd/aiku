<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 16 Feb 2025 17:57:53 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {

        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_vip')->index()->default('false')->comment('VIP customer');
            $table->unsignedSmallInteger('as_organisation_id')->index()->nullable()->comment('Indicate customer is an organisation in this group');
            $table->foreign('as_organisation_id')->references('id')->on('organisations')->nullOnDelete();
            $table->unsignedSmallInteger('as_employee_id')->index()->nullable()->comment('Indicate customer is an employee');
            $table->foreign('as_employee_id')->references('id')->on('employees')->nullOnDelete();
        });


        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_vip')->index()->default('false')->comment('Indicate if invoice is for a VIP customer');
            $table->unsignedSmallInteger('as_organisation_id')->index()->nullable()->comment('Indicate if invoice is for an organisation in this group');
            $table->foreign('as_organisation_id')->references('id')->on('organisations')->nullOnDelete();
            $table->unsignedSmallInteger('as_employee_id')->index()->nullable()->comment('Indicate if invoice is for an employee');
            $table->foreign('as_employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_vip')->index()->default('false')->comment('Indicate if order is for a VIP customer');
            $table->unsignedSmallInteger('as_organisation_id')->index()->nullable()->comment('Indicate if order is for an organisation in this group');
            $table->foreign('as_organisation_id')->references('id')->on('organisations')->nullOnDelete();
            $table->unsignedSmallInteger('as_employee_id')->index()->nullable()->comment('Indicate if order is for an employee');
            $table->foreign('as_employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->boolean('is_vip')->index()->default('false')->comment('Indicate if delivery note  is for a VIP customer');
            $table->unsignedSmallInteger('as_organisation_id')->index()->nullable()->comment('Indicate if delivery note is for an organisation in this group');
            $table->foreign('as_organisation_id')->references('id')->on('organisations')->nullOnDelete();
            $table->unsignedSmallInteger('as_employee_id')->index()->nullable()->comment('Indicate if delivery note is for an employee');
            $table->foreign('as_employee_id')->references('id')->on('employees')->nullOnDelete();
        });
    }


    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_vip');
            $table->dropColumn('as_organisation_id');
            $table->dropColumn('as_employee_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_vip');
            $table->dropColumn('as_organisation_id');
            $table->dropColumn('as_employee_id');
        });

        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('is_vip');
            $table->dropColumn('as_organisation_id');
            $table->dropColumn('as_employee_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('is_vip');
            $table->dropColumn('as_organisation_id');
            $table->dropColumn('as_employee_id');
        });
    }
};
