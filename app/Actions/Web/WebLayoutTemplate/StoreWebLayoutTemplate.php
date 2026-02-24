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

        return $modelData;
    }

    public function parseWebBlockListToLayout(array $webBlocks): array
    {
        $listWebBlocks = [];
        // TODO CHECK FOR SYSTEM WEB BLOCK, DO NOT COPY EVERYTHING IF IT IS
        foreach ($webBlocks as $index => $item) {
            $keepData = Arr::only($item, ['name', 'show', 'type']);
            data_set($keepData, 'layout', data_get($item, 'web_block.layout'));
            data_set($listWebBlocks, $item['type'], $keepData);
        }

        return $listWebBlocks;
    }

    public function rules(): array
    {
        return [
            'label'                     =>      ['required', 'string'],
            'block.*'                   =>      ['required', 'array'],
            'block.web_block'           =>      ['sometimes', 'array'],
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request): WebLayoutTemplate
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $this->validatedData);
    }
}
