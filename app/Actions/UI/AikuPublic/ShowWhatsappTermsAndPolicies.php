<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsController;

class ShowWhatsappTermsAndPolicies
{
    use AsController;

    public function handle(): View
    {
        return view('aiku-public.whatsapp-terms-policies', [
            'effectiveDate' => Carbon::parse('2026-09-01'),
            'contactEmail' => 'hello@aiku.io',
        ]);
    }
}
