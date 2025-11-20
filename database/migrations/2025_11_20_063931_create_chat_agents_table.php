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
            $table->increments('id');
            $table->unsignedInteger('user_id');
            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users');


            $table->integer('max_concurrent_chats')->default(10);
            $table->boolean('is_online')->default(false);
            $table->integer('current_chat_count')->default(0);
            $table->json('specialization')->nullable(); // ["billing", "technical", "sales"]
            $table->boolean('auto_accept')->default(true);
            $table->tinyInteger('is_available')->default(1);

            $table->index('user_id');
            $table->timestampsTz();

            $table->softDeletes();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chat_agents');
    }
};
