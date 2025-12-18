<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Create return_stats table for return statistics
 */

use App\Enums\Dispatching\Return\ReturnItemStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('return_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('return_id')->index();
            $table->foreign('return_id')->references('id')->on('returns')->cascadeOnDelete();

            $table->unsignedSmallInteger('number_items')->default(0)->comment('current number of items');

            foreach (ReturnItemStateEnum::cases() as $case) {
                $table->unsignedSmallInteger('number_items_state_'.$case->snake())->default(0);
            }

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_stats');
    }
};
