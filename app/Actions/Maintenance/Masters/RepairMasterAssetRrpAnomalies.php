<?php

namespace App\Actions\Maintenance\Masters;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterAssetRrpAnomalies
{
    use AsAction;

    public const float NUMERIC_12_3_MAX = 999999999.999;

    public const float MARKUP_MIN = 1.5;

    public const float MARKUP_MAX = 5.0;

    public const float IMPLAUSIBLE_MARKUP = 5.9;

    public string $commandSignature = 'repair:master_asset_rrp_anomalies {master_shop} {--tolerance=0.5 : Max relative deviation from FX rate before a RRP is flagged} {--include-inactive : Also process masters with status false} {--fix : Write corrections (default is report only)}';

    /**
     * @param array<string, float> $exchangeRates  currency code => rate from GBP
     */
    public function handle(MasterAsset $masterAsset, array $exchangeRates, float $tolerance, bool $fix, ?Command $command = null, bool $includeInactive = false): array
    {
        if (!$masterAsset->status && !$includeInactive) {
            return [];
        }

        $rrps     = $masterAsset->master_rrps ?? [];
        $units    = max((float) $masterAsset->units, 1);
        $gbpValue = (float) data_get($rrps, 'GBP.value');
        $findings = [];

        foreach ($this->unitsInflatedCurrencies($masterAsset, $rrps, $units) as $currencyCode => $entry) {
            $value     = (float) data_get($entry, 'value');
            $suggested = round($value / $units, 2);
            $findings[] = $this->finding($masterAsset, $currencyCode, $entry, $value, $suggested, $suggested, $units, false);
        }

        foreach ($findings ? [] : $rrps as $currencyCode => $entry) {
            $value = (float) data_get($entry, 'value');
            if ($value <= 0) {
                continue;
            }

            $overflowRisk = $value * $units > self::NUMERIC_12_3_MAX;

            if ($currencyCode == 'GBP' || !$gbpValue) {
                if ($overflowRisk) {
                    $findings[] = $this->finding($masterAsset, $currencyCode, $entry, $value, null, null, $units, true);
                }
                continue;
            }

            $rate = $exchangeRates[$currencyCode] ?? null;
            if (!$rate) {
                continue;
            }

            $expected  = $gbpValue * $rate;
            $deviation = $value / $expected;

            if (!$overflowRisk && $deviation <= 1 + $tolerance && $deviation >= 1 / (1 + $tolerance)) {
                continue;
            }

            $suggested = $this->suggestedValue($value, $expected, $units);

            $findings[] = $this->finding($masterAsset, $currencyCode, $entry, $value, $expected, $suggested, $units, $overflowRisk);
        }

        $fixedCurrencies = [];
        foreach ($findings as $finding) {
            if ($finding['suggested'] && !$finding['independent']) {
                data_set($rrps, $finding['currency'].'.value', $finding['suggested']);
                $fixedCurrencies[$finding['currency']] = $finding['suggested'];
            }
        }

        if ($fix && $fixedCurrencies) {
            $modelData = ['master_rrps' => $rrps];
            if (isset($fixedCurrencies['EUR'])) {
                $modelData['rrp'] = $fixedCurrencies['EUR'];
            }
            $masterAsset->updateQuietly($modelData);
        }

        $findings = array_merge($findings, $this->processProducts($masterAsset, $rrps, $units, $fix));

        foreach ($findings as $finding) {
            $command?->line($this->format($finding, $fix));
        }

        return $findings;
    }

    private ?\Illuminate\Support\Collection $currenciesById = null;

    private function currenciesById(): \Illuminate\Support\Collection
    {
        return $this->currenciesById ??= Currency::all()->keyBy('id');
    }

    /**
     * The rrp×units defect: the undivided markup is already implausible, every major carries the
     * identical corrupt ratio, and dividing by units restores a plausible retail markup. All three
     * conditions are required — a healthy low-units asset can show a 3x markup that survives a
     * division, and a high ratio alone also describes legitimate multipacks whose rrp is quoted
     * per single item, which dividing would price at cost.
     *
     * @param  array<string, mixed>  $rrps
     * @return array<string, mixed>  currencies to divide, empty when the defect is not proven
     */
    private function unitsInflatedCurrencies(MasterAsset $masterAsset, array $rrps, float $units): array
    {
        if ($units <= 1) {
            return [];
        }

        $prices  = $masterAsset->master_prices ?? [];
        $markups = [];

        foreach (['GBP', 'EUR'] as $code) {
            $price = (float) data_get($prices, "$code.value");
            $rrp   = (float) data_get($rrps, "$code.value");
            if ($price <= 0 || $rrp <= 0) {
                return [];
            }

            if ($rrp / $price <= self::IMPLAUSIBLE_MARKUP) {
                return [];
            }

            $markup = ($rrp / $units) / $price;
            if ($markup < self::MARKUP_MIN || $markup > self::MARKUP_MAX) {
                return [];
            }
            $markups[] = $markup;
        }

        if (abs($markups[0] / $markups[1] - 1) > 0.01) {
            return [];
        }

        return array_filter($rrps, fn ($entry) => (float) data_get($entry, 'value') > 0);
    }

    private function suggestedValue(float $value, float $expected, float $units): ?float
    {
        // ponytail: only units and units² divisors, the double-multiplication bug produces exactly these;
        // acceptance band fixed at 20% so small-units assets can't sneak wrong divisions through
        foreach ([$units, $units * $units] as $divisor) {
            if ($divisor <= 1) {
                continue;
            }
            $candidate = $value / $divisor;
            if (abs($candidate / $expected - 1) <= 0.2) {
                return round($candidate, 2);
            }
        }

        return null;
    }

    private function processProducts(MasterAsset $masterAsset, array $rrps, float $units, bool $fix): array
    {
        $findings   = [];
        $currencies = $this->currenciesById();

        /** @var Product $product */
        foreach ($masterAsset->products()->whereNot('products.state', ProductStateEnum::DISCONTINUED)->with(['family', 'shop'])->get() as $product) {
            $currencyCode = $currencies->get($product->currency_id)?->code;
            $masterValue  = (float) data_get($rrps, "$currencyCode.value");
            $productRrp   = (float) $product->rrp;

            if (!$masterValue || $productRrp <= 0 || abs($productRrp / $masterValue - 1) < 0.01) {
                continue;
            }

            $matchesBug = false;
            foreach ([$units, $units * $units] as $divisor) {
                if ($divisor > 1 && abs(($productRrp / $divisor) / $masterValue - 1) <= 0.15) {
                    $matchesBug = true;
                }
            }

            if (!$matchesBug) {
                continue;
            }

            $independent = !data_get($product->shop->settings, 'catalog.follow_master_pricing', true)
                || $product->family?->not_follow_master_prices
                || $product->not_follow_master_prices;

            $findings[] = [
                'code'        => $masterAsset->code,
                'units'       => $units,
                'currency'    => $currencyCode,
                'scope'       => "product:{$product->shop->code}",
                'value'       => $productRrp,
                'expected'    => $masterValue,
                'suggested'   => $independent ? null : $masterValue,
                'independent' => (bool) $independent,
                'overflow'    => false,
            ];

            if ($fix && !$independent) {
                UpdateProduct::make()->action($product, ['rrp' => $masterValue]);
            }
        }

        return $findings;
    }

    private function finding(MasterAsset $masterAsset, string $currencyCode, array $entry, float $value, ?float $expected, ?float $suggested, float $units, bool $overflowRisk): array
    {
        return [
            'code'        => $masterAsset->code,
            'units'       => $units,
            'currency'    => $currencyCode,
            'scope'       => 'master',
            'value'       => $value,
            'expected'    => $expected,
            'suggested'   => $suggested,
            'independent' => (bool) data_get($entry, 'independent'),
            'overflow'    => $overflowRisk,
        ];
    }

    private function format(array $finding, bool $fix): string
    {
        $parts = [
            "[{$finding['code']}]",
            $finding['scope'],
            $finding['currency'],
            'units='.$finding['units'],
            'value='.$finding['value'],
            'expected≈'.($finding['expected'] !== null ? round($finding['expected'], 2) : 'n/a'),
        ];

        if ($finding['overflow']) {
            $parts[] = 'OVERFLOW RISK';
        }

        if ($finding['suggested']) {
            $parts[] = ($fix && !$finding['independent'] ? 'FIXED to ' : 'suggest ').$finding['suggested'];
        } else {
            $parts[] = 'no confident fix, review manually';
        }

        if ($finding['independent']) {
            $parts[] = '(independent, not touched)';
        }

        return implode(' | ', $parts);
    }

    public function asCommand(Command $command): int
    {
        ini_set('memory_limit', '2G');
        DB::connection()->disableQueryLog();

        $masterShop = MasterShop::where('slug', $command->argument('master_shop'))->firstOrFail();
        $tolerance  = (float) $command->option('tolerance');
        $fix        = (bool) $command->option('fix');

        if (!$fix) {
            $command->warn('REPORT ONLY: pass --fix to write corrections');
        }

        $currencyCodes = DB::table('master_assets')
            ->where('master_shop_id', $masterShop->id)
            ->whereNotNull('master_rrps')
            ->selectRaw('distinct jsonb_object_keys(master_rrps) as code')
            ->pluck('code');

        $gbp = Currency::where('code', 'GBP')->firstOrFail();

        $exchangeRates = [];
        foreach (Currency::whereIn('code', $currencyCodes)->get() as $currency) {
            $rate = GetCurrencyExchange::run($gbp, $currency);
            if (!$rate && $currency->code != 'GBP') {
                $command->warn("No exchange rate GBP → {$currency->code}, that currency will be skipped");
            }
            $exchangeRates[$currency->code] = $rate;
        }

        $includeInactive = (bool) $command->option('include-inactive');

        $totalFindings = 0;
        MasterAsset::where('master_shop_id', $masterShop->id)
            ->whereNotNull('master_rrps')
            ->when(!$includeInactive, fn ($query) => $query->where('status', true))
            ->orderBy('id')
            ->chunkById(250, function ($masterAssets) use ($exchangeRates, $tolerance, $fix, $command, $includeInactive, &$totalFindings) {
                foreach ($masterAssets as $masterAsset) {
                    $totalFindings += count($this->handle($masterAsset, $exchangeRates, $tolerance, $fix, $command, $includeInactive));
                }
            });

        $command->info("Done: $totalFindings anomalies ".($fix ? 'processed' : 'found'));

        return 0;
    }
}
