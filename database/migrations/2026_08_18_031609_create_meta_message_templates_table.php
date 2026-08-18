<?php

use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('meta_message_templates', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedSmallInteger('meta_channel_id')->index();
            $table->foreign('meta_channel_id')->references('id')->on('meta_channels')->onUpdate('cascade')->onDelete('cascade');

            $table->string('template_id')->index();
            $table->string('name');
            $table->string('parameter_format')->nullable();
            $table->string('language')->nullable();
            $table->string('status')->nullable();
            $table->string('category')->nullable();
            $table->boolean('disable_ios_autofill')->default(false);
            $table->boolean('is_primary_device_delivery_only')->default(false);
            $table->timestampTz('synchronize_at')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestampsTz();
            $this->softDeletes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_message_templates');
    }
};
