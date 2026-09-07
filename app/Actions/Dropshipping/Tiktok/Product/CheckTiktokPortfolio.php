<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckTiktokPortfolio
{
    use AsAction;

    public function handle(Portfolio $portfolio): Portfolio
    {
        if (!$portfolio->customerSalesChannel) {
            return $portfolio;
        }

        /** @var TiktokUser $tiktokUser */
        $tiktokUser = $portfolio->customerSalesChannel->user;

        if (!$tiktokUser instanceof TiktokUser) {
            return $portfolio;
        }

        $hasValidProductId     = CheckIfTiktokProductIDIsValid::run($portfolio->platform_product_id);
        $productExistsInTiktok = false;
        $listingIsUsable       = false;
        $tiktokProduct         = null;

        if ($hasValidProductId) {
            $result        = CheckIfProductExistInTiktok::run($tiktokUser, $portfolio);
            $tiktokProduct = Arr::get($result, 'error') === true ? null : Arr::get($result, 'data');

            $productExistsInTiktok = filled(Arr::get($tiktokProduct, 'id'));
            $listingIsUsable       = $productExistsInTiktok && !in_array(Arr::get($tiktokProduct, 'status'), ['DELETED', 'FAILED']);
        }

        $matches = $listingIsUsable ? [] : self::possibleMatches($tiktokUser, $portfolio);

        $matchData = [
            'number_matches' => count($matches),
            'matches_labels' => Arr::pluck($matches, 'name'),
            'raw_data'       => $matches
        ];

        $portfolio->update([
            'has_valid_platform_product_id'    => $hasValidProductId,
            'exist_in_platform'                => $productExistsInTiktok,
            'platform_status'                  => $listingIsUsable,
            'platform_possible_matches'        => $matchData,
            'number_platform_possible_matches' => count($matches)
        ]);

        if ($productExistsInTiktok) {
            $data = $portfolio->data;
            data_set($data, 'tiktok_product', $tiktokProduct);

            UpdatePortfolio::run($portfolio, array_filter([
                'data'           => $data,
                'upload_warning' => self::auditWarning($tiktokProduct)
            ]));
        }

        return $portfolio;
    }

    /**
     * TikTok can only be asked for whole seller SKUs and its product search carries no images,
     * so a match is the listing with this portfolio's SKU, in the shape the retina table expects.
     *
     * @return array<int, array{id: string, name: string, images: array<int, array{src: string}>}>
     */
    public static function possibleMatches(TiktokUser $tiktokUser, Portfolio $portfolio): array
    {
        if (blank($portfolio->sku)) {
            return [];
        }

        $result = $tiktokUser->getProducts(['seller_skus' => [$portfolio->sku]], ['page_size' => 10]);

        if (Arr::get($result, 'error') === true) {
            return [];
        }

        return collect(Arr::get($result, 'data.products', []))
            ->filter(fn ($product) => filled(Arr::get($product, 'id')) && Arr::get($product, 'status') !== 'DELETED')
            ->map(fn ($product) => [
                'id'     => (string) Arr::get($product, 'id'),
                'name'   => (string) Arr::get($product, 'title', Arr::get($product, 'id')),
                'images' => array_map(fn ($url) => ['src' => $url], Arr::get($product, 'main_images.0.urls', []))
            ])
            ->values()
            ->toArray();
    }

    /**
     * A listing that failed TikTok's audit exists but is not on sale, so it counts as broken and
     * the reasons are kept where the retina table shows upload warnings.
     */
    public static function auditWarning(?array $tiktokProduct): ?string
    {
        if (Arr::get($tiktokProduct, 'audit.status') !== 'FAILED' && Arr::get($tiktokProduct, 'status') !== 'FAILED') {
            return null;
        }

        $reasons = collect(Arr::get($tiktokProduct, 'audit_failed_reasons', []))
            ->flatMap(fn ($failure) => array_merge(Arr::get($failure, 'reasons', []), Arr::get($failure, 'suggestions', [])))
            ->filter()
            ->unique()
            ->implode(' ');

        return $reasons ?: __('TikTok rejected this listing in review, check it in Seller Center.');
    }

    public function getCommandSignature(): string
    {
        return 'tiktok:check_portfolio {portfolio_id}';
    }

    public function asCommand(Command $command)
    {
        $portfolio = Portfolio::find($command->argument('portfolio_id'));

        $this->handle($portfolio);
    }
}
