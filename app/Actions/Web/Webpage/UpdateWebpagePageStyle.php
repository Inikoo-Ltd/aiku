<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\WebBlockHistory\StoreWebBlockHistory;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * Stores the choices of the workshop's Page style tab so it reopens with them, and writes the style
 * changes the tab worked out into the stored layout of each web block of the page. Only the changed
 * style paths are sent, so data the workshop loads into a block (family, products, prices) never
 * reaches the stored layout. The style reaches the storefront when the webpage is published.
 */
class UpdateWebpagePageStyle extends OrgAction
{
    use WithWebEditAuthorisation;
    use WithActionUpdate;

    /**
     * @param array{page_style: array<string, mixed>, web_blocks?: array<int, array{id: int, changes: array<int, array{path: array<int, string>, value: mixed}>}>} $modelData
     */
    public function handle(Webpage $webpage, array $modelData): Webpage
    {
        $webpage = $this->update($webpage, ['settings' => ['page_style' => $modelData['page_style']]], ['settings']);

        $blockChanges = collect(Arr::get($modelData, 'web_blocks', []))->keyBy('id');
        if ($blockChanges->isEmpty()) {
            return $webpage;
        }

        $modelHasWebBlocks = $webpage->modelHasWebBlocks()->whereIn('id', $blockChanges->keys())->with('webBlock')->get();
        foreach ($modelHasWebBlocks as $modelHasWebBlock) {
            $this->applyStyleChanges($modelHasWebBlock, $blockChanges->get($modelHasWebBlock->id)['changes']);
        }

        if ($modelHasWebBlocks->isNotEmpty()) {
            UpdateWebpageContent::run($webpage);
        }

        return $webpage;
    }

    /**
     * @param array<int, array{path: array<int, string>, value: mixed}> $changes
     */
    private function applyStyleChanges(ModelHasWebBlocks $modelHasWebBlock, array $changes): void
    {
        $layout = json_decode(json_encode($modelHasWebBlock->webBlock->layout), true) ?? [];

        foreach ($changes as $change) {
            data_set($layout, array_merge(['data', 'fieldValue'], $change['path']), $change['value']);
        }

        $this->update($modelHasWebBlock->webBlock, ['layout' => $layout]);
        StoreWebBlockHistory::make()->action($modelHasWebBlock, ['layout' => $layout]);
    }

    public function rules(): array
    {
        $colour = ['sometimes', 'nullable', 'string', 'max:64'];

        return [
            'page_style'                     => ['required', 'array'],
            'page_style.colors'              => ['sometimes', 'array'],
            'page_style.colors.text'         => $colour,
            'page_style.colors.background'   => $colour,
            'page_style.colors.primary'      => $colour,
            'page_style.colors.button_text'  => $colour,
            'page_style.font_family'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'page_style.button'              => ['sometimes', 'array'],
            'page_style.button.radius'       => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'page_style.spacing'             => ['sometimes', 'array'],
            'page_style.spacing.vertical'    => ['sometimes', 'nullable', 'integer', 'min:0', 'max:400'],
            'page_style.spacing.horizontal'  => ['sometimes', 'nullable', 'integer', 'min:0', 'max:400'],
            'web_blocks'                     => ['sometimes', 'array'],
            'web_blocks.*.id'                => ['required', 'integer'],
            'web_blocks.*.changes'           => ['required', 'array'],
            'web_blocks.*.changes.*.path'    => ['required', 'array', 'min:1', 'max:12'],
            'web_blocks.*.changes.*.path.*'  => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'web_blocks.*.changes.*.value'   => ['present'],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $this->validatedData);
    }

    public function action(Webpage $webpage, array $modelData): Webpage
    {
        $this->asAction = true;

        $this->initialisationFromShop($webpage->shop, $modelData);

        return $this->handle($webpage, $this->validatedData);
    }
}
