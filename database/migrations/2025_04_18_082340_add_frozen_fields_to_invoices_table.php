<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Apr 2025 16:23:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('customer_name', 256)->nullable()->index();
            $table->string('customer_contact_name', 256)->nullable();
            $table->string('tax_number')->nullable()->index();
            $table->string('tax_number_status')->nullable();
            $table->boolean('tax_number_valid')->nullable();
            $table->string('identity_document_type')->nullable();
            $table->string('identity_document_number')->nullable()->collation('und_ci');
        });
    }


    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'customer_contact_name',
                'tax_number',
                'tax_number_valid',
                'identity_document_type',
                'identity_document_number',
            ]);
        });
    }
};
