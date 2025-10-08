<?php

use App\Enums\Announcement\AnnouncementStateEnum;
use App\Enums\Announcement\AnnouncementStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onUpdate('cascade')->onDelete('cascade');

            $table->string('code')->index()->nullable();
            $table->string('ulid')->unique()->index();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->jsonb('fields');
            $table->jsonb('container_properties');

            $table->unsignedInteger('website_id')->nullable()->after('id');

            $table->unsignedSmallInteger('unpublished_snapshot_id')->nullable()->index();
            $table->unsignedSmallInteger('live_snapshot_id')->nullable()->index();
            $table->dateTimeTz('ready_at')->nullable();
            $table->dateTimeTz('live_at')->nullable();
            $table->dateTimeTz('closed_at')->nullable();
            $table->string('published_checksum')->nullable()->index();
            $table->string('state')->default(AnnouncementStateEnum::IN_PROCESS->value);
            $table->boolean('is_dirty')->default(true);

            $table->string('schedule_at')->nullable();
            $table->string('schedule_finish_at')->nullable();
            $table->string('status')->default(AnnouncementStatusEnum::INACTIVE->value);
            $table->jsonb('settings')->default('{}');

            $table->longText('compiled_layout')->nullable();
            $table->longText('text')->nullable();

            $table->string('published_message')->nullable();
            $table->jsonb('published_settings')->nullable();

            $table->jsonb('published_fields')->nullable();

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
