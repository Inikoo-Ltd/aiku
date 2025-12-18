<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 14:12:00 Makassar Time
 * Description: Drop existing returns and return_items tables to recreate with new structure
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Drop existing tables if they exist (they were created outside of migrations)
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }

    public function down(): void
    {
        // This migration is intentionally not reversible
        // The tables will be recreated by the next migration
    }
};
