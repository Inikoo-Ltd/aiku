<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 17-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Web\Redirect\StoreRedirect;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateWebpages;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CloseWebpage extends OrgAction
{
    use AsAction;
    use WithAttributes;


    public function handle(Webpage $webpage, array $modelData): Webpage
    {
        StoreRedirect::make()->action(
            $webpage,
            [
                'type' => Arr::get($modelData, 'redirect_type', RedirectTypeEnum::PERMANENT->value),
                'path' => Arr::get($modelData, 'redirect_path', ''),
            ]
        );

        $webpage->update([
            'state' => WebpageStateEnum::CLOSED->value,
        ]);
        WebsiteHydrateWebpages::dispatch($webpage->website);

        return $webpage;
    }

    public function rules(): array
    {
        return [
            'redirect_type',
            ['required', Rule::enum(RedirectTypeEnum::class)],

            'redirect_path' => [
                'nullable',
                'string'
            ],
        ];
    }

    public function action(Webpage $webpage, array $modelData): Webpage
    {
        return $this->handle($webpage, $modelData);
    }
}
