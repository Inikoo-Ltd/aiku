<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) { // this for history of print
            $table->increments('id');
            $table->foreignId('printer_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('content_type');
            $table->text('content');
            $table->string('source')->nullable();
            $table->unsignedBigInteger('printnode_job_id')->nullable();
            $table->string('status')->default('queued');
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
