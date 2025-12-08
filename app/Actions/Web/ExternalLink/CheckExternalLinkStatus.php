<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\ExternalLink;

use App\Actions\OrgAction;
use Exception;
use Illuminate\Support\Facades\Http;

class CheckExternalLinkStatus extends OrgAction
{
    public function handle(string $url): string
    {
        try {
            $result = Http::timeout(10)->get($url);

            return $result->status();
        } catch (Exception $e) {
            return 'error';
        }
    }
}
