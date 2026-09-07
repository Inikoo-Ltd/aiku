<?php

/*
 * Author Louis Perez
 * Created on 10-08-2026-16h-28m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\OrgAction;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

class FetchWebLayoutTemplateDetail extends OrgAction
{
    public function handle(Webpage $webpage, WebLayoutTemplate $layoutTemplate)
    {
        $ignoredDynamicTemplate = WebBlockTemplateEnum::allTemplateCodes();
        $parse = function ($item) use ($ignoredDynamicTemplate) {

            return [
                'id'            => data_get($item, 'id'),
                'ulid'          => data_get($item, 'ulid'),
                'show'          => data_get($item, 'show'),
                'type'          => data_get($item, 'type'),
                'position'      => data_get($item, 'position'),
                'visibility'    => data_get($item, 'visibility'),
                'must_have'     => in_array(data_get($item, 'type'), $ignoredDynamicTemplate),
            ];
        };

        return [
            'current'   => array_map($parse, data_get($webpage->unpublishedSnapshot?->layout, 'web_blocks', [])),
            'incoming'  => array_values(array_map($parse, array_filter($layoutTemplate->blocks, fn ($item) => !in_array(data_get($item, 'type'), $ignoredDynamicTemplate))))
        ];
    }

    public function jsonResponse(array $template): array
    {
        return $template;
    }

    public function asController(Webpage $webpage, WebLayoutTemplate $layoutTemplate, ActionRequest $request)
    {
        $this->initialisationFromGroup($webpage->group, $request);

        return $this->handle($webpage, $layoutTemplate);
    }
}
