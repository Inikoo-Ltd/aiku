<?php

/*
 * Author Louis Perez
 * Created on 11-08-2026-15h-06m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\Maintenance\Web\WithRepairWebpages;
use App\Actions\OrgAction;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Actions\Web\Webpage\WithStoreWebpage;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class ApplyWebLayoutTemplate extends OrgAction
{
    use WithStoreWebpage;
    use WithRepairWebpages;

    public function handle(Webpage $webpage, array $modelData)
    {
        $template   = WebLayoutTemplate::find(data_get($modelData, 'template_id'));

        if (!$template) {
            abort(404, 'Invalid Template being selected');
        }

        $webpage = DB::transaction(function () use ($webpage, $modelData, $template) {

            $webBlocks  = data_get($modelData, 'blocks');

            foreach ($webBlocks as $key => $webBlock) {

                // If it's from incoming, the WebBlock will be created based on selected template fieldValue data
                if (!$webBlock['id']) {
                    $visibility = [
                        'show_logged_in'    => data_get($webBlock, 'visibility.in', true),
                        'show_logged_out'   => data_get($webBlock, 'visibility.out', true),
                        'show'              => data_get($webBlock, 'show', true),
                    ];

                    $fieldValue = Arr::only(array_find($template->blocks, fn ($item) => $item['type'] == $webBlock['type']), 'fieldValue');

                    $newBlock = $this->createWebBlock(
                        $webpage,
                        $webBlock['type'],
                        $fieldValue,
                        $visibility
                    );

                    data_set($webBlocks, "{$key}.id", $newBlock->id);
                }

                DB::table('model_has_web_blocks')
                    ->where('id', $webBlock['id'])
                    ->update(['position' => $webBlock['position']]);
            }

            $unusedId = $webpage->modelHasWebBlocks()->whereNotIn('id', data_get($webBlocks, '*.id'))->pluck('id')->toArray();

            $this->deleteWebBlocksByModelHasID($webpage, $unusedId);

            return $webpage;
        });

        UpdateWebpageContent::run($webpage->refresh());
    }

    public function rules(): array
    {
        return [
            'blocks'                => ['required', 'array'],
            'blocks.*.id'           => ['nullable', 'numeric'],
            'blocks.*.show'         => ['required', 'boolean'],
            'blocks.*.type'         => ['required', 'string'],
            'blocks.*.visibility'   => ['required', 'array'],
            'blocks.*.position'     => ['required', 'numeric'],
            'template_id'           => ['required', 'numeric', 'exists:web_layout_templates,id']
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request)
    {
        $this->initialisation($webpage->organisation, $request);

        return $this->handle($webpage, $this->validatedData);
    }
}
