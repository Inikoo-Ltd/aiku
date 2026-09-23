<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 00:30:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebVital;

use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreWebVitalSample
{
    use AsAction;

    private const array METRICS = ['lcp', 'inp', 'cls', 'fcp', 'ttfb'];

    public function handle(Website $website, array $modelData): void
    {
        $sample = Arr::only($modelData, self::METRICS);

        if (!array_filter($sample, fn ($value) => $value !== null)) {
            return;
        }

        $webpageId = Arr::get($modelData, 'webpage_id');

        if ($webpageId && !Webpage::where('id', $webpageId)->where('website_id', $website->id)->exists()) {
            $webpageId = null;
        }

        DB::table('web_vital_samples')->insert([
            'website_id' => $website->id,
            'webpage_id' => $webpageId,
            'device'     => $modelData['device'],
            'created_at' => now(),
        ] + array_merge(array_fill_keys(self::METRICS, null), $sample));
    }

    public function rules(): array
    {
        return [
            'webpage_id' => ['nullable', 'integer'],
            'device'     => ['required', 'in:desktop,phone'],
            'lcp'        => ['nullable', 'integer', 'between:0,120000'],
            'inp'        => ['nullable', 'integer', 'between:0,120000'],
            'cls'        => ['nullable', 'numeric', 'between:0,100'],
            'fcp'        => ['nullable', 'integer', 'between:0,120000'],
            'ttfb'       => ['nullable', 'integer', 'between:0,120000'],
        ];
    }

    public function asController(ActionRequest $request): void
    {
        $this->handle($request->website, $request->validated());
    }
}
