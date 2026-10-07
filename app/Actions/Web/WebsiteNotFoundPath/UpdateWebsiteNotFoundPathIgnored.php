<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteNotFoundPath;

use App\Actions\OrgAction;
use App\Actions\Web\Seo\WithSeoEditAuthorisation;
use App\Models\Web\WebsiteNotFoundPath;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class UpdateWebsiteNotFoundPathIgnored extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(WebsiteNotFoundPath $websiteNotFoundPath, bool $isIgnored): WebsiteNotFoundPath
    {
        $websiteNotFoundPath->update(['is_ignored' => $isIgnored]);

        return $websiteNotFoundPath;
    }

    public function rules(): array
    {
        return [
            'is_ignored' => ['required', 'boolean'],
        ];
    }

    public function asController(WebsiteNotFoundPath $websiteNotFoundPath, ActionRequest $request): WebsiteNotFoundPath
    {
        $this->initialisationFromShop($websiteNotFoundPath->website->shop, $request);

        return $this->handle($websiteNotFoundPath, (bool) $this->validatedData['is_ignored']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
