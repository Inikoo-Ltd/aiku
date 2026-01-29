<?php

namespace App\Actions\CRM\Customer\Hydrators;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use App\Services\GeocoderService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Log;

class CustomerHydrateAddressCoordinates implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:customers-address-coordinates {--shop_code= : Filter by Shop Code} {--a|async : Run asynchronously in background queue}';
    public string $commandDescription = 'Hydrate latitude and longitude for customer addresses';

    public function getJobUniqueId(int|null $customerId): string
    {
        return $customerId ?? 'all';
    }

    public function handle(int|null $customerId): void
    {
        if ($customerId === null) {
            return;
        }
        $customer = Customer::with(['address', 'address.country'])->find($customerId);

        if (! $customer || ! $customer->address) {
            return;
        }

        $address = $customer->address;
        if ($address->latitude && $address->longitude) {
            return;
        }

        $countryName = $address->country ? $address->country->name : null;

        if (!$countryName && $address->country_code) {
            $countryName = Country::where('code', $address->country_code)->value('name');
        }

        $addressData = [
            'address_line_1'      => $address->address_line_1,
            'address_line_2'      => $address->address_line_2,
            'dependent_locality'  => $address->dependent_locality,
            'locality'            => $address->locality,
            'administrative_area' => $address->administrative_area,
            'postal_code'         => $address->postal_code,
            'country_code'        => $address->country_code,
            'country_name'        => $countryName,
        ];


        $geocoder = new GeocoderService();
        $result = $geocoder->geocodeLayered($addressData);

        if ($result) {

            $address->update([
                'latitude'           => $result['latitude'],
                'longitude'          => $result['longitude'],
                'geocoding_metadata' => $result,
            ]);

            // Log::info("Updated coordinates for Customer ID {$customerId}");
        } else {
            // Log::warning("Failed to geocode Customer ID {$customerId}");
        }
    }

    public function asCommand(Command $command): void
    {
        $command->info('Starting hydration of customer address coordinates...');

        $query = Customer::has('address');

        if ($shopCode = $command->option('shop_code')) {
            $shop = Shop::where('code', $shopCode)->first();

            if (! $shop) {
                $command->error("Shop with code '{$shopCode}' not found.");
                return;
            }

            $query->where('shop_id', $shop->id);
            $command->info("Filtering by Shop: {$shop->name} ({$shopCode})");
        }

        $async = $command->option('async');

        if ($async) {
            $command->info("Running in ASYNC mode. Jobs will be pushed to 'low-priority' queue.");
        }

        $totalCustomers = $query->count();

        $bar = $command->getOutput()->createProgressBar($totalCustomers);
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        $delayCounter = 0;
        $query->chunkById(100, function ($customers) use ($bar, $async, &$delayCounter) {
            foreach ($customers as $customer) {
                if ($async) {
                    static::dispatch($customer->id)
                        ->onQueue('low-priority')
                        ->delay(now()->addSeconds($delayCounter));
                    $delayCounter++;
                } else {
                    $this->handle($customer->id);
                    usleep(1100000);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();

        if ($async) {
            $command->info("Success! All jobs dispatched to queue 'low-priority'.");
        } else {
            $command->info('All customer addresses hydrated successfully!');
        }
    }
}
