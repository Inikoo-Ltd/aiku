<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordRailClick
{
    use AsAction;

    public function handle(string $button): void
    {
        $key = 'rail_clicks:'.now()->toDateString();
        Redis::hincrby($key, $button, 1);
        Redis::expire($key, 60 * 86400);
    }

    public function rules(): array
    {
        return [
            'button' => ['required', 'string', 'alpha_dash', 'max:40'],
        ];
    }

    public function asController(ActionRequest $request): Response
    {
        $this->handle($request->validated('button'));

        return response()->noContent();
    }
}
