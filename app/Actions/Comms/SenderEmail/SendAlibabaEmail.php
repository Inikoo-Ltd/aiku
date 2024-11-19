<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\SenderEmail;

use AlibabaCloud\SDK\Dm\V20151123\Dm;
use AlibabaCloud\SDK\Dm\V20151123\Models\SingleSendMailRequest;
use App\Models\Comms\DispatchedEmail;
use Darabonba\OpenApi\Models\Config;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsCommand;

class SendAlibabaEmail
{
    use AsAction;
    use AsCommand;

    public string $commandSignature = 'send-email:alibaba';

    /**
     * @throws \Throwable
     */
    public function handle($subject, $html, DispatchedEmail $dispatchedEmail, $sender, $unsubscribeUrl): void
    {
        $config                  = new Config();
        $config->accessKeyId     = Arr::get($dispatchedEmail->mailshot->shop->data, 'alibaba_access_key');
        $config->accessKeySecret = Arr::get($dispatchedEmail->mailshot->shop->data, 'alibaba_access_secret');
        $config->endpoint        = 'dm.ap-'.Arr::get($dispatchedEmail->mailshot->shop->data, 'alibaba_region').'.aliyuncs.com';

        $request                 = new SingleSendMailRequest();
        $dm                      = new Dm($config);

        $request->subject        = $subject;
        $request->accountName    = $sender;
        $request->toAddress      = $dispatchedEmail->email->address;
        $request->addressType    = 0;
        $request->replyToAddress = false;
        $request->htmlBody       = $html;

        $dm->singleSendMail($request);
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(): int
    {
        $this->handle();

        return 0;
    }
}
