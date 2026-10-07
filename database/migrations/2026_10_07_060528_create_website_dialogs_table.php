<?php

use App\Enums\Web\WebsiteDialog\WebsiteDialogStateEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_dialogs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();

            $table->string('ulid')->unique();
            $table->string('name');
            $table->string('template_code')->nullable();
            $table->jsonb('fields')->default('{}');
            $table->jsonb('container_properties')->default('{}');
            $table->jsonb('settings')->default('{}');
            $table->jsonb('published_layout')->nullable();

            $table->unsignedInteger('unpublished_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_snapshot_id')->nullable()->index();
            $table->string('published_checksum')->nullable();
            $table->string('published_message')->nullable();

            $table->string('state')->default(WebsiteDialogStateEnum::IN_PROCESS->value)->index();
            $table->string('status')->default(WebsiteDialogStatusEnum::INACTIVE->value)->index();
            $table->boolean('is_dirty')->default(true);

            $table->dateTimeTz('ready_at')->nullable();
            $table->dateTimeTz('live_at')->nullable();
            $table->dateTimeTz('closed_at')->nullable();
            $table->dateTimeTz('schedule_at')->nullable();
            $table->dateTimeTz('schedule_finish_at')->nullable();

            $table->unsignedBigInteger('paused_by_website_dialog_id')->nullable();
            $table->foreign('paused_by_website_dialog_id')->references('id')->on('website_dialogs')->nullOnDelete();
            $table->dateTimeTz('paused_until')->nullable();

            $table->timestampsTz();
        });

        Schema::table('website_stats', function (Blueprint $table) {
            $table->integer('number_website_dialogs')->default(0);
            $table->integer('number_active_website_dialogs')->default(0);
            $table->integer('number_inactive_website_dialogs')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('website_stats', function (Blueprint $table) {
            $table->dropColumn([
                'number_website_dialogs',
                'number_active_website_dialogs',
                'number_inactive_website_dialogs',
            ]);
        });

        Schema::dropIfExists('website_dialogs');
    }
};
