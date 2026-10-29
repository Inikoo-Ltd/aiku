<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('supplier_portal_contacts', function (Blueprint $table) {
            $table->boolean('is_subscribed_to_newsletter')->default(true)->comment('gets the newsletter of shops set to send it to suppliers');
            $table->dateTimeTz('newsletter_unsubscribed_at')->nullable();
        });

        Schema::create('supplier_portal_contact_has_dispatched_emails', function (Blueprint $table) {
            $table->unsignedInteger('supplier_portal_contact_id')->index();
            $table->foreign('supplier_portal_contact_id', 'spc_hde_contact_id_foreign')->references('id')->on('supplier_portal_contacts')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('dispatched_email_id')->index()->comment('no foreign key: adding one would lock dispatched_emails while newsletters send');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_portal_contact_has_dispatched_emails');
        Schema::table('supplier_portal_contacts', function (Blueprint $table) {
            $table->dropColumn(['is_subscribed_to_newsletter', 'newsletter_unsubscribed_at']);
        });
    }
};
