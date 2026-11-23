<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Temporary (INI-524, Nov 2026): clicks on the Aura box buttons in email chats, one aura_button_usage
 * row per day, user, shop and button, to decide which buttons stay. Drop the table and this tracking
 * once that is decided.
 */
class RecordAuraButtonClick
{
    use AsAction;

    public const array BUTTONS = ['close_answer_in', 'old_note', 'old_note_beside', 'notes_timeline', 'ticket_chip'];

    public function handle(int $userId, int $shopId, string $button): void
    {
        DB::statement(
            'INSERT INTO aura_button_usage (date, user_id, shop_id, button, clicks) VALUES (CURRENT_DATE, ?, ?, ?, 1)
             ON CONFLICT (date, user_id, shop_id, button) DO UPDATE SET clicks = aura_button_usage.clicks + 1',
            [$userId, $shopId, $button]
        );
    }

    public function rules(): array
    {
        return [
            'button'  => ['required', 'string', Rule::in(self::BUTTONS)],
            'shop_id' => ['required', 'integer', Rule::exists('shops', 'id')->where('group_id', request()->user()->group_id)],
        ];
    }

    public function asController(ActionRequest $request): Response
    {
        $this->handle($request->user()->id, (int) $request->validated('shop_id'), $request->validated('button'));

        return response()->noContent();
    }
}
