<?php

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('website_llms_txt', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);

            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();

            $table->string('filename')->nullable()->comment('Original filename');
            $table->string('path')->comment('Storage path');
            $table->unsignedInteger('file_size')->comment('Size in bytes');
            $table->text('content')->nullable()->comment('File content for quick serving');
            $table->string('checksum')->nullable()->comment('MD5 checksum for integrity');

            $table->boolean('is_active')->default(true)->index();
            $table->boolean('use_fallback')->default(true)->comment('Use global fallback if no file');

            $table->unsignedInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestampTz('uploaded_at')->nullable();

            $table->timestampsTz();

            $table->unique(['website_id', 'is_active', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_llms_txt');
    }
};
