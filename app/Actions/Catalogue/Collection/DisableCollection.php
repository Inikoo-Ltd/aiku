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
use App\Actions\Web\Redirect\StoreRedirect;
use App\Actions\Web\Webpage\CloseWebpage;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Validation\Validator;

class DisableCollection extends OrgAction
{
    use AsAction;
    use WithAttributes;

    private ?Collection $collection;

    public function handle(Collection $collection, array $modelData): void
    {
        $collection->update([
            'state' => CollectionStateEnum::INACTIVE->value,
        ]);

        CloseWebpage::run($collection->webpage);

        StoreRedirect::make()->action(
            $collection->webpage,
            [
                'type' => RedirectTypeEnum::PERMANENT,
                'path' => Arr::get($modelData, 'path'),
            ]
        );
    }

    public function rules()
    {
        return [
            'path' => [
                'required',
                'string'
            ],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->set('path', $this->get('data.path', ''));
    }

    public function afterValidator(Validator $validator): void
    {
        $path = $this->get('path');
        if (Webpage::where('url', $path)->exists()) {
            $validator->errors()->add('path', __('The redirect link already exists in webpages.'));
        }
    }
    public function asController(Collection $collection, ActionRequest $request)
    {
        $this->initialisation($collection->organisation, $request);
        $this->handle($collection, $this->validatedData);
    }
}
