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
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

/**
 * Stores the choices of the workshop's Page style tab so it reopens with them. The style itself
 * is written into the page's web blocks and reaches the storefront when the webpage is published.
 */
class UpdateWebpagePageStyle extends OrgAction
{
    use WithWebEditAuthorisation;
    use WithActionUpdate;

    /**
     * @param array{page_style: array<string, mixed>} $modelData
     */
    public function handle(Webpage $webpage, array $modelData): Webpage
    {
        return $this->update($webpage, ['settings' => ['page_style' => $modelData['page_style']]], ['settings']);
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
        ];
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
