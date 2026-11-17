<?php

namespace App\Actions\Dropshipping\Ankorstore;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * An Ankorstore brand account is read with an API application's client id and secret (Ankorstore, Integrations, API).
 * Orders are imported only from import_from on: staff key in by hand everything before it.
 */
class ConnectAnkorstoreShop
{
    use AsAction;

    public string $commandSignature = 'ankorstore:connect {shop : slug} {from : import orders placed from this time on, e.g. "2026-11-18 08:00"} {--sandbox}';

    public function handle(Shop $shop, string $clientId, #[\SensitiveParameter] string $clientSecret, Carbon $importFrom, bool $sandbox = false): Shop
    {
        $settings = $shop->settings;
        data_set($settings, 'ankorstore.client_id', $clientId);
        data_set($settings, 'ankorstore.client_secret', $clientSecret);
        data_set($settings, 'ankorstore.sandbox', $sandbox);

        $probe           = $shop->replicate();
        $probe->id       = $shop->id;
        $probe->settings = $settings;
        try {
            $me = $probe->ankorstoreRequest('GET', 'me');
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['client_secret' => $exception->getMessage()]);
        }

        data_set($settings, 'ankorstore.account', Arr::get($me, 'data.email'));
        data_set($settings, 'ankorstore.import_from', $importFrom->toIso8601String());
        $shop->settings = $settings;
        $shop->save();

        return $shop;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $shop = $this->handle(
            $shop,
            trim((string)$command->ask('Ankorstore client id')),
            trim((string)$command->secret('Ankorstore client secret')),
            Carbon::parse($command->argument('from')),
            (bool)$command->option('sandbox')
        );

        $command->info('Shop '.$shop->slug.' reads Ankorstore account '.Arr::get($shop->settings, 'ankorstore.account').', orders from '.Arr::get($shop->settings, 'ankorstore.import_from'));

        return 0;
    }
}
