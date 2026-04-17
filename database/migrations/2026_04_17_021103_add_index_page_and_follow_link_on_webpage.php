<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 Apr 2026 12:06:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('webpages', function ($table) {
            $table->boolean('index_page')->default('true');
            $table->boolean('follow_link')->default('true');
        });
    }


    public function down(): void
    {
        Schema::table('webpages', function ($table) {
            $table->dropColumn([
                'index_page',
                'follow_link',
            ]);
        });
    }
};
