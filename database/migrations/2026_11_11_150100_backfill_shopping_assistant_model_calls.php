<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The monthly budget sums shopping_assistant_model_calls from now on. Each assistant message stored
 * before that table existed becomes one call row with the turn's totals, dated as the message, so
 * the spend of the current month is the same before and after the deploy. Running it again adds nothing.
 */
return new class () extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
            insert into shopping_assistant_model_calls
                (shopping_assistant_conversation_id, shopping_assistant_message_id, model, prompt_tokens, completion_tokens, cost, latency_ms, created_at)
            select messages.shopping_assistant_conversation_id, messages.id, messages.model, messages.prompt_tokens, messages.completion_tokens, messages.cost, messages.latency_ms, messages.created_at
            from shopping_assistant_messages messages
            where messages.role = 'assistant'
                and (messages.cost > 0 or messages.model_calls > 0)
                and not exists (select 1 from shopping_assistant_model_calls calls where calls.shopping_assistant_message_id = messages.id)
            SQL);
    }

    public function down(): void
    {
    }
};
