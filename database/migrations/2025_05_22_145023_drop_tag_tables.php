<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 22 May 2025 22:50:33 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCRMStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasCRMStats;

    public function up(): void
    {
        Schema::dropIfExists('tag_crm_stats');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }


    public function down(): void
    {
        /**
         * This method is intentionally left empty because:
         * 1. These tables (tags, taggables, tag_crm_stats) are being permanently removed from system
         * 2. Recreating them would require complex schema definitions that are no longer maintained
         * 3. The application has been refactored to no longer use these tables
         * 4. If needed, refer to the original migration files for their structure:
         *    - 2023_10_30_102546_create_tag_tables.php
         *    - 2023_11_15_050812_create_tag_crm_stats_table.php
         */
    }
};
