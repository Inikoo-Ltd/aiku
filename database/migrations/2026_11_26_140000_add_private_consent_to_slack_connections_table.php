<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('slack_connections', function (Blueprint $table) {
            $table->dateTimeTz('private_consented_at')->nullable()->comment('When the person said yes to bringing their private conversations; null = off');
            $table->string('private_consent_version')->nullable()->comment('Version of the consent text the person agreed to');
            $table->string('private_terms_version')->nullable()->comment('Version of the small print the person agreed to');
        });

        Schema::create('slack_private_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('slack_user_id')->nullable();
            $table->string('event')->comment('granted, withdrawn or lapsed (Slack no longer holds the private permissions)');
            $table->string('consent_version')->nullable();
            $table->string('terms_version')->nullable();
            $table->text('scopes')->nullable()->comment('Comma list of the user scopes Slack granted at the time');
            $table->dateTimeTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_private_consents');
        Schema::table('slack_connections', function (Blueprint $table) {
            $table->dropColumn(['private_consented_at', 'private_consent_version', 'private_terms_version']);
        });
    }
};
