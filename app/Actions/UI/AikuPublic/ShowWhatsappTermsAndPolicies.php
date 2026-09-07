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

    public const string EFFECTIVE_DATE = '2026-09-01';

    public static function effectiveDate(): Carbon
    {
        return Carbon::parse(self::EFFECTIVE_DATE);
    }

    public function handle(): View
    {
        return view('aiku-public.whatsapp-terms-policies', [
            'effectiveDate' => self::effectiveDate(),
            'contactEmail' => 'hello@aiku.io',
        ]);
    }
}
