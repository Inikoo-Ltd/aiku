<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\SysAdmin;

use App\Actions\CRM\Customer\PdfCustomerLetterOfAuthorisation;
use App\Actions\RetinaAction;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class PdfRetinaLetterOfAuthorisation extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root && PdfCustomerLetterOfAuthorisation::isAvailable($request->user()->customer->shop);
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return PdfCustomerLetterOfAuthorisation::make()->handle($this->customer);
    }
}
