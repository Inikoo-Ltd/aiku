<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\EmailAddress;

use App\Actions\Comms\EmailAddress\Traits\AwsClient;
use App\Actions\Web\Website\Utils\AddDomainDnsRecordCloudflare;
use App\Enums\Web\DnsCloudflareTypeEnum;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class VerifyDomainEmailAddress
{
    use AsAction;
    use AwsClient;

    public string $commandSignature   = 'domain:verify {domain} {zone}';
    public string $commandDescription = 'Verify Domain In AWS to Cloudflare';

    public function handle(string $domain, string $zoneId): void
    {
        $result = $this->getSesClient()->verifyDomainIdentity([
            'Domain' => $domain,
        ]);

        AddDomainDnsRecordCloudflare::run($zoneId, [
            [
                'type'    => DnsCloudflareTypeEnum::TXT->value,
                'name'    => $domain,
                'content' => $result['VerificationToken'],
                'proxied' => false
            ],
            [
                'type'    => DnsCloudflareTypeEnum::A->value,
                'name'    => $domain,
                'content' => '65.109.156.41',
                'proxied' => true
            ]
        ]);
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command->argument('domain'), $command->argument('zone'));
    }
}
