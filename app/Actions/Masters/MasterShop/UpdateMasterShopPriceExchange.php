<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Jul 2026 15:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterShopPriceExchange extends OrgAction
{
    use WithMastersEditAuthorisation;

    protected ?int $auditUserID = null;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(MasterShop $masterShop, array $modelData): MasterShop
    {
        $currencyCode   = $modelData['currency'];
        $priceExchanges = $masterShop->price_exchanges ?? [];

        $runningProgress = RecalculateMasterShopMinorCurrencyPrices::getProgress($masterShop, $currencyCode);
        if ($runningProgress && !in_array($runningProgress['state'], ['finished', 'failed'])) {
            throw ValidationException::withMessages([
                'currency' => __('A price recalculation for :currency is already running, wait for it to finish', ['currency' => $currencyCode])
            ]);
        }

        if ($modelData['is_major']) {
            $priceExchanges[$currencyCode] = ['is_major' => true];
        } else {
            $majorCurrencyCode = $modelData['major'];

            if (!data_get($priceExchanges, "$majorCurrencyCode.is_major")) {
                throw ValidationException::withMessages([
                    'major' => __(':currency is not a major currency of this master shop', ['currency' => $majorCurrencyCode])
                ]);
            }

            $followers = collect($priceExchanges)
                ->filter(fn (array $exchangeData) => !($exchangeData['is_major'] ?? false) && ($exchangeData['major'] ?? null) === $currencyCode)
                ->keys();

            if ($followers->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'currency' => __('Cannot make :currency minor, these currencies follow it: :followers', [
                        'currency'  => $currencyCode,
                        'followers' => $followers->join(', ')
                    ])
                ]);
            }

            $priceExchanges[$currencyCode] = [
                'is_major'        => false,
                'major'           => $majorCurrencyCode,
                'exchange'        => $modelData['exchange'],
                'fraction_digits' => (int)($modelData['fraction_digits'] ?? 2),
            ];
            if (!empty($modelData['increment'])) {
                $priceExchanges[$currencyCode]['increment'] = (float)$modelData['increment'];
            }
        }

        $masterShop->update(['price_exchanges' => $priceExchanges]);

        if (!$modelData['is_major']) {
            RecalculateMasterShopMinorCurrencyPrices::setProgress($masterShop, $currencyCode, [
                'state' => 'queued',
                'done'  => 0,
                'total' => 0,
            ]);
            RecalculateMasterShopMinorCurrencyPrices::dispatch($masterShop, $currencyCode, $this->auditUserID);
        }

        return $masterShop;
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'is_major' => ['required', 'boolean'],
            'major'    => ['required_if:is_major,false', 'string', 'size:3', 'different:currency'],
            'exchange'        => ['required_if:is_major,false', 'numeric', 'gt:0'],
            'fraction_digits' => ['sometimes', 'integer', 'between:0,2'],
            'increment'       => ['sometimes', 'nullable', 'numeric', 'gt:0', 'lt:1'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(MasterShop $masterShop, ActionRequest $request): MasterShop
    {
        $this->auditUserID = $request->user()?->id;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterShop, $this->validatedData);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function htmlResponse(): \Illuminate\Http\RedirectResponse
    {
        return back();
    }

    public function action(MasterShop $masterShop, array $modelData): MasterShop
    {
        $this->asAction = true;
        $this->initialisationFromGroup($masterShop->group, $modelData);

        return $this->handle($masterShop, $this->validatedData);
    }

    public string $commandSignature = 'master_shop:price_exchange
        {master_shop : Master shop slug}
        {currency : Currency code, e.g. CZK}
        {--make-major : Make this a major currency}
        {--major= : Major currency to follow (defaults to current)}
        {--exchange= : Exchange rate from the major (defaults to current)}
        {--fraction-digits= : 0 for whole-number prices, 2 for cents (defaults to current)}
        {--increment= : Round converted prices/RRPs up to this step, e.g. 0.05 (defaults to current; pass 0 to clear)}
        {--force : Skip the confirmation prompt}';

    public function asCommand(Command $command): int
    {
        $masterShop   = MasterShop::where('slug', $command->argument('master_shop'))->firstOrFail();
        $currencyCode = strtoupper($command->argument('currency'));
        $current      = data_get($masterShop->price_exchanges, $currencyCode);

        $command->info("Current $currencyCode config: ".($current ? json_encode($current) : 'none'));

        $modelData = [
            'currency' => $currencyCode,
            'is_major' => (bool)$command->option('make-major'),
        ];

        if (!$modelData['is_major']) {
            $modelData['major']    = strtoupper($command->option('major') ?? data_get($current, 'major') ?? '');
            $modelData['exchange'] = $command->option('exchange') !== null
                ? (float)$command->option('exchange')
                : data_get($current, 'exchange');

            $fractionDigits = $command->option('fraction-digits') ?? data_get($current, 'fraction_digits');
            if ($fractionDigits !== null) {
                $modelData['fraction_digits'] = (int)$fractionDigits;
            }

            $increment = $command->option('increment') ?? data_get($current, 'increment');
            if ($increment) {
                $modelData['increment'] = (float)$increment;
            }
        }

        $command->info("New $currencyCode config: ".json_encode($modelData));

        if (!$modelData['is_major']) {
            $command->warn('This will recalculate every product price in the shops using this currency (runs in Horizon)');
        }

        if (!$command->option('force') && !$command->confirm('Apply?')) {
            $command->line('Aborted, nothing changed');

            return 1;
        }

        try {
            $this->action($masterShop, $modelData);
        } catch (ValidationException $e) {
            $command->error(collect($e->errors())->flatten()->join(' '));

            return 1;
        }

        $command->info('Saved'.($modelData['is_major'] ? '' : ', price recalculation dispatched'));

        return 0;
    }
}
