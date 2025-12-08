<?php

use App\Stubs\Migrations\HasCommsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCommsStats;

    public function up(): void
    {
        Schema::create('org_post_room_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('org_post_room_id')->nullable();
            $table->foreign('org_post_room_id')->references('id')->on('org_post_rooms');
            $table = $this->outboxesStats($table);
            $table = $this->mailshotsStats($table);
            $table = $this->dispatchedEmailStats($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_post_room_stats');
    }
};
