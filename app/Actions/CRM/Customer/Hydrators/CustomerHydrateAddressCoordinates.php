<?php

namespace App\Actions\CRM\Customer\Hydrators;

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

    public string $commandSignature = 'hydrate:customers-address-coordinates';
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
        // this option is to skip if coordinates already exist
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
        $totalCustomers = $query->count();

        $bar = $command->getOutput()->createProgressBar($totalCustomers);
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        $query->chunkById(100, function ($customers) use ($bar) {
            foreach ($customers as $customer) {
                $this->handle($customer->id);
                usleep(50000);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
        $command->info('All customer addresses hydrated successfully!');
    }
}
