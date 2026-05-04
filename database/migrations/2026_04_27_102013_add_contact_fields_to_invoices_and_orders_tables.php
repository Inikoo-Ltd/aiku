<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Apr 2026 16:11:15 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('email', 255)->nullable();
            $table->string('phone', 255)->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('email', 255)->nullable();
            $table->string('phone', 255)->nullable();
            $table->string('contact_name', 256)->nullable();
            $table->string('company_name', 256)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['email', 'phone', 'contact_name', 'company_name']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['email', 'phone', 'contact_name', 'company_name']);
        });
    }
};
