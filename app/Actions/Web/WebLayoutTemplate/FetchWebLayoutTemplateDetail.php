<?php

/*
 * Author Louis Perez
 * Created on 10-08-2026-16h-28m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\OrgAction;
use App\Http\Resources\Web\WebLayoutTemplateWebBlocksResource;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

class FetchWebLayoutTemplateDetail extends OrgAction
{
    public function handle(Webpage $webpage, WebLayoutTemplate $layoutTemplate)
    {
        $parse = function ($item) {
            return [
                'id'            => data_get($item, 'id'),
                'show'          => data_get($item, 'show'),
                'type'          => data_get($item, 'type'),
                'position'      => data_get($item, 'position'),
                'visibility'    => data_get($item, 'visibility'),
            ];
        };

        return [
            'current'   => array_map($parse, data_get($webpage->unpublishedSnapshot?->layout, 'web_blocks', [])),
            'incoming'  => array_map($parse, $layoutTemplate->blocks)
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
