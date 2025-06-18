<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 17-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\CloseWebpage;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Collection;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Validation\Validator;

class DisableCollection extends OrgAction
{
    use AsAction;
    use WithAttributes;


    public function handle(Collection $collection, array $modelData): void
    {
        $collection->update([
            'state' => CollectionStateEnum::INACTIVE->value,
        ]);

        CloseWebpage::run(
            $collection->webpage,
            [
                'redirect_type' => RedirectTypeEnum::PERMANENT,
                'to_webpage_id' => Arr::get($modelData, 'to_webpage_id', $collection->webpage->website->storefront_id),
            ]
        );

    }

    public function rules(): array
    {
        return [
            'to_webpage_id' => [
                'required',
                Rule::exists(Webpage::class, 'id')->where('website_id', $this->shop->website->id)->where('state', WebpageStateEnum::LIVE),
            ],
        ];
    }

    public function prepareForValidation(): void
    {
        $path = $this->get('path');
        if ($path == '/') {
            $path = '';
        }
        $webpage = Webpage::where('website_id', $this->shop->website->id)->where('url', $path)->first();
        if ($webpage) {
            $this->set('to_webpage_id', $webpage->id);
        }

    }


    public function afterValidator(Validator $validator, ActionRequest $request): void
    {

        $collection = $request->route('collection');

        if ($collection->webpage && $collection->webpage->id == $this->get('to_webpage_id')) {
            $validator->errors()->add('path', __('The redirect webpage is same as current webpage.'));
        }



    }

    public function asController(Collection $collection, ActionRequest $request): void
    {
        $this->initialisationFromShop($collection->shop, $request);
        $this->handle($collection, $this->validatedData);
    }
}
