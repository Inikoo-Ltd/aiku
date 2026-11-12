<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 16:02:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX supplier_agreement_acceptances_once_per_version ON supplier_agreement_acceptances (supplier_id, supplier_agreement_version_id)');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION supplier_agreement_versions_frozen() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.state <> 'draft' THEN
                        RAISE EXCEPTION 'a published supplier agreement version cannot be deleted';
                    END IF;
                    RETURN OLD;
                END IF;
                IF OLD.state <> 'draft' AND NEW.state = 'draft' THEN
                    RAISE EXCEPTION 'a published supplier agreement version cannot go back to draft';
                END IF;
                IF OLD.state = 'retired' AND NEW.state <> 'retired' THEN
                    RAISE EXCEPTION 'a retired supplier agreement version cannot be published again';
                END IF;
                IF OLD.state <> 'draft' AND (NEW.body IS DISTINCT FROM OLD.body OR NEW.title IS DISTINCT FROM OLD.title
                    OR NEW.version IS DISTINCT FROM OLD.version OR NEW.change_summary IS DISTINCT FROM OLD.change_summary
                    OR NEW.requires_reacceptance IS DISTINCT FROM OLD.requires_reacceptance OR NEW.published_at IS DISTINCT FROM OLD.published_at
                    OR NEW.group_id IS DISTINCT FROM OLD.group_id) THEN
                    RAISE EXCEPTION 'a published supplier agreement version cannot be edited';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS supplier_agreement_acceptances_once_per_version');
    }
};
