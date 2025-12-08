<?php

use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('shopify_users', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->string('slug')->unique()->collation('und_ns');
            $table->boolean('status')->default(true)->index();
            $table->string('name')->index();
            $table->boolean('shopify_grandfathered')->default(false);
            $table->string('shopify_namespace')->nullable()->default(null);
            $table->boolean('shopify_freemium')->default(false);
            $table->integer('plan_id')->unsigned()->nullable();
            $table->string('username')->index();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('auth_type')->default(WebUserAuthTypeEnum::DEFAULT->value);
            $table->rememberToken();
            $table->unsignedSmallInteger('number_api_tokens')->default(0);
            $table->text('about')->nullable();
            $table->date('password_updated_at')->nullable();
            $table->integer('theme_support_level')->nullable();
            $table->jsonb('data');
            $table->jsonb('settings');

            $table->boolean('reset_password')->default(false);
            $table->unsignedSmallInteger('language_id')->default(68);
            $table->foreign('language_id')->references('id')->on('languages');
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media')->onDelete('cascade');
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['email']);
            $table->unique(['username']);
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_users');
    }
};
