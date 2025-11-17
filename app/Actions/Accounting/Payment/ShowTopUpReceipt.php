<?php

/*
 * author Louis Perez
 * created on 17-11-2025-14h-34m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Accounting\Payment;

use App\Actions\OrgAction;
use App\Actions\Accounting\TopUp\WithSingleTopUpReceipt;
use App\Models\Accounting\TopUp;
use Exception;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use App\Models\Accounting\Payment;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Sentry;
use Symfony\Component\HttpFoundation\Response;

class ShowTopUpReceipt extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithSingleTopUpReceipt;

    public function handle(TopUp $topUp, array $options): Response
    {
        return $this->processDataExportPdf($topUp);
    }

    public function inOrganisation(Organisation $organisation, Payment $payment, TopUp $topUp, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($topUp, $this->validatedData);
    }
}
