<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 16-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Actions\Traits\WithOmegaData;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class OmegaInvoice extends OrgAction
{
    use WithOmegaData;
    public function handle(Invoice $invoice): string
    {

        $text = "R00\tT00\r\n";

        $text .= $this->getOmegaExportText($invoice, 'SK');

        return iconv(mb_detect_encoding($text), 'ISO-8859-15//IGNORE', mb_convert_encoding($text, 'UTF-8', 'auto'));
    }


    public function asController(Organisation $organisation, Invoice $invoice, ActionRequest $request): Response
    {
        $this->initialisationFromShop($invoice->shop, $request);



        $omegaText = $this->handle($invoice);


        $filename = $invoice->slug.'.txt';

        return response($omegaText, 200)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }


}
