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
use Webklex\PHPIMAP\ClientManager;

class ImapResearch extends OrgAction
{
    use AsAction;

    public function handle()
    {

    }

    public string $commandSignature = 'xxx';

    public function asCommand($command)
    {

        $client = new ClientManager();
        $client = $client->make([
            'host' => 'imap.gmail.com',
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => false, //Just for testing, otherwise true.
            'username' => env('IMAP_USERNAME'),
            'password' => env('IMAP_PASSWORD'),
            'protocol' => 'imap'
        ]);


        $client->connect();
    }





}
