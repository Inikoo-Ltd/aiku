<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 01 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Maintenance\Web\RepairScriptWebBlocksBase64Files;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\Webpage;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class RepairWebpageBase64File extends OrgAction
{
    use WithWebEditAuthorisation;

    public function handle(Webpage $webpage, ModelHasWebBlocks $modelHasWebBlock, string $dataUri): string
    {
        $webBlock = $modelHasWebBlock->webBlock;

        $url = $webBlock->webBlockType->code === 'script'
            ? RepairScriptWebBlocksBase64Files::make()->repairFile($webBlock, $dataUri, $webpage)
            : null;

        if ($url === null) {
            throw ValidationException::withMessages([
                'data_uri' => __('This file cannot be uploaded, it is not a valid image or PDF of this script block.'),
            ]);
        }

        return $url;
    }

    public function rules(): array
    {
        return [
            'data_uri' => ['required', 'string', 'starts_with:data:'],
        ];
    }

    public function asController(Webpage $webpage, ModelHasWebBlocks $modelHasWebBlock, ActionRequest $request): string
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $modelHasWebBlock, $this->validatedData['data_uri']);
    }

    /**
     * @return array{url: string}
     */
    public function jsonResponse(string $url): array
    {
        return ['url' => $url];
    }
}
