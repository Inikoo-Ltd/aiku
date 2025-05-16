<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 16-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Imap;

use App\Actions\OrgAction;
use Lorisleiva\Actions\Concerns\AsAction;

class ImapResearch extends OrgAction
{
    use AsAction;

    public function handle()
    {

    }

    public string $commandSignature = 'xxx';

    public function asCommand($command)
    {

        $domain = 'dev@aw-advantage.com';
        $mxRecords = dns_get_record($domain, DNS_MX);
        dd($mxRecords);

        if (!empty($mxRecords)) {
            $mxHost = $mxRecords[0]['target'];
            echo "Mail server: $mxHost";
        }

    }





}
