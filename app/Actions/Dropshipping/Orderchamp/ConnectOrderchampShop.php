<?php

namespace App\Actions\Dropshipping\Orderchamp;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * An Orderchamp supplier account is read with a private access token (Orderchamp backoffice, Apps & Integrations, API).
 * Orders are imported only from import_from on: staff key in by hand everything before it.
 */
class ConnectOrderchampShop
{
    use AsAction;

    public string $commandSignature = 'orderchamp:connect {shop : slug} {from : import orders placed from this time on, e.g. "2026-11-18 08:00"}';

    public function handle(Shop $shop, #[\SensitiveParameter] string $accessToken, Carbon $importFrom): Shop
    {
        $settings = $shop->settings;
        data_set($settings, 'orderchamp.access_token', $accessToken);

        $probe           = $shop->replicate();
        $probe->settings = $settings;
        try {
            $account = Arr::get($probe->orderchampQuery('{ account { name type slug } }'), 'account', []);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['access_token' => 'Orderchamp refused the token: '.$exception->getMessage()]);
        }

        data_set($settings, 'orderchamp.account', Arr::get($account, 'slug'));
        data_set($settings, 'orderchamp.import_from', $importFrom->toIso8601String());
        $shop->settings = $settings;
        $shop->save();

        return $shop;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $shop = $this->handle($shop, trim((string)$command->secret('Orderchamp access token')), Carbon::parse($command->argument('from')));

        $command->info('Shop '.$shop->slug.' reads Orderchamp account '.Arr::get($shop->settings, 'orderchamp.account').', orders from '.Arr::get($shop->settings, 'orderchamp.import_from'));

        return 0;
    }
}
