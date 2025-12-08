<?php

use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Enums\CRM\WebUser\WebUserTypeEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('tiktok_users', function (Blueprint $table) {
            $table->id();

            /** @var Blueprint $table */
            $table = $this->groupOrgRelationship($table);
            $table->string('tiktok_id')->index()->unique();
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->boolean('status')->default(true)->index();
            $table->string('name')->index();
            $table->string('username')->index()->nullable();
            $table->text('access_token')->nullable();
            $table->string('access_token_expire_in')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('refresh_token_expire_in')->nullable();
            $table->string('auth_type')->default(WebUserAuthTypeEnum::DEFAULT->value);
            $table->string('state')->default(WebUserTypeEnum::WEB->value);
            $table->jsonb('data');
            $table->jsonb('settings');

            $table->timestampsTz();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_users');
    }
};
