<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Temporary rail analytics (Nov 2026) to decide how to simplify the right rail.
 * One rail_usage row per day, user, view and button; button '' marks the user was seen in that view.
 * Drop the table and this tracking once the redesign is decided.
 */
class RecordRailClick
{
    use AsAction;

    public const array VIEWS = ['extended', 'normal', 'compact', 'micro'];

    public function handle(int $userId, string $view, ?string $button): void
    {
        DB::statement(
            'INSERT INTO rail_usage (date, user_id, view, button, clicks) VALUES (CURRENT_DATE, ?, ?, ?, ?)
             ON CONFLICT (date, user_id, view, button) DO UPDATE SET clicks = rail_usage.clicks + EXCLUDED.clicks',
            [$userId, $view, $button ?? '', $button ? 1 : 0]
        );
    }

    public function rules(): array
    {
        return [
            'view'   => ['required', 'string', Rule::in(self::VIEWS)],
            'button' => ['nullable', 'string', 'alpha_dash', 'max:40'],
        ];
    }

    public function asController(ActionRequest $request): Response
    {
        $this->handle($request->user()->id, $request->validated('view'), $request->validated('button'));

        return response()->noContent();
    }
}
