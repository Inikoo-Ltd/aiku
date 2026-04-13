<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 10 April 2026 16:40:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\InertiaAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectMailshotTemplateWorkshopLink extends InertiaAction
{
    use AsAction;

    public function handle(Organisation $organisation, Shop $shop, Mailshot $mailshot, EmailTemplate $template): RedirectResponse
    {
        // Store template data in session for workshop to use
        Session::flash('selected_template', [
            'id' => $template->id,
            'subject' => $template->name,
            'compiled_layout' => $template->compiled_layout,
            'shop_name' => $template->shop?->name ?? 'Unknown',
        ]);

        // Determine workshop route based on mailshot type
        $routeName = $mailshot->type->value === 'marketing'
            ? 'grp.org.shops.show.marketing.mailshots.workshop'
            : 'grp.org.shops.show.marketing.newsletters.workshop';

        return redirect()->route($routeName, [
            'organisation' => $organisation->slug,
            'shop' => $shop->slug,
            'mailshot' => $mailshot->slug
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Organisation $organisation, Shop $shop, Mailshot $mailshot, EmailTemplate $template, ActionRequest $request): RedirectResponse
    {
        return $this->handle($organisation, $shop, $mailshot, $template);
    }
}
