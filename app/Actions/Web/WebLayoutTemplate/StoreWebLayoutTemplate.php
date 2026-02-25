<?php

/*
 * author Louis Perez
 * created on 24-02-2026-16h-53m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Enums\Web\WebBlockType\WebBlockSystemEnum;
use App\Enums\Web\WebLayoutTemplate\WebLayoutTemplateType;
use App\Models\Web\WebBlock;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreWebLayoutTemplate extends OrgAction
{
    use WithWebEditAuthorisation;

    public function handle(Webpage|WebBlock $parent, array $modelData): WebLayoutTemplate
    {
        if($parent instanceof Webpage){
            $modelData = $this->handleWebpageLayout($parent, $modelData);
        }

        $webBlockLayout = DB::transaction(function () use ($modelData) {
            $webBlockLayout = WebLayoutTemplate::create($modelData);
            
            return $webBlockLayout;
        });

        return $webBlockLayout;
    }

    public function handleWebpageLayout(Webpage $webpage, array $modelData): array
    {
        $orders = [];
        $webBlocks = Arr::pull($modelData, 'block.web_blocks');
        
        data_forget($modelData, 'block');
        foreach ($webBlocks as $index => $item) {
            data_set($orders, $item['type'], $index);
        }
        $data = [];
        data_set($data, 'orders', $orders);
        data_set($data, 'layout', $this->parseWebBlockListToLayout($webBlocks));
        
        
        // Handles layout for Webpage/WebBlock in future
        data_set($modelData, 'type', WebLayoutTemplateType::WEBPAGE);
        // Handles scope limitations (Product Page / Collections / Family, etc)
        data_set($modelData, 'scope', $webpage->sub_type);
        // Will insert to column
        data_set($modelData, 'data', $data);
        data_set($modelData, 'author_id', auth()->user()->id);

        return $modelData;
    }

    public function parseWebBlockListToLayout(array $webBlocks): array
    {
        $listWebBlocks = [];
        $listSystemWebBlock = WebBlockSystemEnum::listSystemWebBlock();

        foreach ($webBlocks as $index => $item) {
            $keepData = Arr::only($item, ['name', 'show', 'type']);
            // Check for system web blocks, this will be generated on BE based on saved styles of each website, no need to save the layout
            if(!in_array(data_get($item, 'type'), $listSystemWebBlock)){
                data_set($keepData, 'layout', data_get($item, 'web_block.layout'));
            }
            data_set($listWebBlocks, $item['type'], $keepData);
        }

        return $listWebBlocks;
    }

    public function rules(): array
    {
        return [
            'label'                     =>      ['required', 'string', 'unique:web_layout_templates,label'],
            'block.*'                   =>      ['required', 'array'],
            'block.web_block'           =>      ['sometimes', 'array'],
        ];
    }

    public function getValidationMessages(): array 
    {
        return [
            // Label
            'label.required' => 'A template label is required.',
            'label.string'   => 'The template label must be a valid text value.',
            'label.unique'   => 'This template label already exists. Please choose a different label.',

            // Block (generic + wildcard)
            'block.required' => 'Error in parsing Web Block formats, please contact the developer in charge.',
            'block.array'    => 'Error in parsing Web Block formats, please contact the developer in charge.',
            'block.*'        => 'Error in parsing Web Block formats, please contact the developer in charge.',
            'block.web_block.array' => 'Error in parsing Web Block formats, please contact the developer in charge.',
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request): WebLayoutTemplate
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $this->validatedData);
    }
}
