<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('supplier_agreement_versions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('version');
            $table->string('title');
            $table->text('body')->comment('Markdown, English, the binding text');
            $table->text('change_summary')->nullable();
            $table->boolean('requires_reacceptance')->default(true);
            $table->string('state')->index();
            $table->dateTimeTz('published_at')->nullable();
            $table->unsignedInteger('published_by_user_id')->nullable();
            $table->foreign('published_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->dateTimeTz('retired_at')->nullable();
            $table->timestampsTz();
            $table->unique(['group_id', 'version']);
        });

        DB::statement("CREATE UNIQUE INDEX supplier_agreement_versions_one_published ON supplier_agreement_versions (group_id) WHERE state = 'published'");

        Schema::create('supplier_agreement_acceptances', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('supplier_id')->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->unsignedInteger('supplier_agreement_version_id')->index();
            $table->foreign('supplier_agreement_version_id')->references('id')->on('supplier_agreement_versions');
            $table->unsignedInteger('supplier_share_link_id')->nullable()->index();
            $table->foreign('supplier_share_link_id')->references('id')->on('supplier_share_links');
            $table->unsignedInteger('supplier_portal_profile_id')->nullable();
            $table->string('supplier_legal_name');
            $table->string('authorised_person');
            $table->string('position');
            $table->dateTimeTz('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->unsignedBigInteger('ip_geolocation_id')->nullable()->comment('city, from MaxMind, cached per ip');
            $table->text('user_agent')->nullable();
            $table->string('locale', 8)->nullable()->comment('language the portal was shown in');
            $table->string('body_hash', 64)->comment('sha256 of the accepted Markdown body');
            $table->dateTimeTz('created_at');
        });

        DB::statement(<<<'SQL'
            CREATE FUNCTION supplier_agreement_acceptances_immutable() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE' AND NEW.ip_geolocation_id IS DISTINCT FROM OLD.ip_geolocation_id
                    AND (to_jsonb(NEW) - 'ip_geolocation_id') = (to_jsonb(OLD) - 'ip_geolocation_id') THEN
                    RETURN NEW;
                END IF;
                RAISE EXCEPTION 'supplier agreement acceptances cannot be changed or deleted';
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER supplier_agreement_acceptances_immutable BEFORE UPDATE OR DELETE ON supplier_agreement_acceptances FOR EACH ROW EXECUTE FUNCTION supplier_agreement_acceptances_immutable()');

        DB::statement(<<<'SQL'
            CREATE FUNCTION supplier_agreement_versions_frozen() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.state <> 'draft' THEN
                        RAISE EXCEPTION 'a published supplier agreement version cannot be deleted';
                    END IF;
                    RETURN OLD;
                END IF;
                IF OLD.state <> 'draft' AND (NEW.body IS DISTINCT FROM OLD.body OR NEW.title IS DISTINCT FROM OLD.title
                    OR NEW.version IS DISTINCT FROM OLD.version OR NEW.change_summary IS DISTINCT FROM OLD.change_summary
                    OR NEW.requires_reacceptance IS DISTINCT FROM OLD.requires_reacceptance OR NEW.published_at IS DISTINCT FROM OLD.published_at) THEN
                    RAISE EXCEPTION 'a published supplier agreement version cannot be edited';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER supplier_agreement_versions_frozen BEFORE UPDATE OR DELETE ON supplier_agreement_versions FOR EACH ROW EXECUTE FUNCTION supplier_agreement_versions_frozen()');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedInteger('supplier_agreement_acceptance_id')->nullable()->index();
            $table->foreign('supplier_agreement_acceptance_id')->references('id')->on('supplier_agreement_acceptances');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_agreement_acceptance_id']);
            $table->dropColumn('supplier_agreement_acceptance_id');
        });
        Schema::dropIfExists('supplier_agreement_acceptances');
        Schema::dropIfExists('supplier_agreement_versions');
        DB::statement('DROP FUNCTION IF EXISTS supplier_agreement_acceptances_immutable()');
        DB::statement('DROP FUNCTION IF EXISTS supplier_agreement_versions_frozen()');
    }
};
