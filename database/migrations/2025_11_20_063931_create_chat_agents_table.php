<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Stubs\Migrations\HasSoftDeletes;


return new class extends Migration
{
     use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('chat_agents', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('user_id')->index()->unique();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->unsignedSmallInteger('max_concurrent_chats')->default(10);
            $table->boolean('is_online')->index()->default(false);
            $table->boolean('is_available')->index()->default(false);

            $table->unsignedSmallInteger('current_chat_count')->default(0);
            $table->json('specialization')->nullable(); // ["billing", "technical", "sales"]
            $table->boolean('auto_accept')->default(true);

            $table->timestampsTz();
            $table->softDeletes();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chat_agents');
    }
};
