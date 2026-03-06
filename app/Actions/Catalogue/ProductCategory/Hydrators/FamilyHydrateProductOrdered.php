<?php

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\FamilyHasProductOrdered;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FamilyHydrateProductOrdered implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(ProductCategory $family): string
    {
        return $family->id;
    }

    public function handle(ProductCategory $family): void
    {
        if ($family->type !== ProductCategoryTypeEnum::FAMILY) {
            return;
        }

        $threeMonthsAgo = Carbon::now()->subMonths(3);

        $latestUniqueTransactions = DB::table('transactions')
            ->select([
                'transactions.*',
                DB::raw("
                    ROW_NUMBER() OVER (
                        PARTITION BY transactions.model_id
                        ORDER BY transactions.submitted_at DESC
                    ) as rn
                ")
            ])
            ->whereNotNull('transactions.submitted_at')
            ->where('transactions.model_type', 'Product')
            ->whereIn('transactions.state', [
                TransactionStateEnum::SUBMITTED,
                TransactionStateEnum::IN_WAREHOUSE,
                TransactionStateEnum::HANDLING,
                TransactionStateEnum::PACKED,
                TransactionStateEnum::FINALISED,
            ])
            ->where('transactions.submitted_at', '>=', $threeMonthsAgo);

        $latestUniqueTransactionsQuery = DB::query()
            ->fromSub($latestUniqueTransactions, 'ranked_transactions')
            ->where('rn', 1);

        $orderedProducts = DB::table('products')
            ->joinSub($latestUniqueTransactionsQuery, 'latest_tx', function ($join) {
                $join->on('latest_tx.model_id', '=', 'products.id');
            })
            ->leftJoin('webpages', function ($join) {
                $join->on('webpages.model_id', '=', 'products.id')
                    ->where('webpages.model_type', 'Product')
                    ->where('webpages.state', WebpageStateEnum::LIVE->value);
            })
            ->leftJoin('customers', 'latest_tx.customer_id', '=', 'customers.id')
            ->leftJoin('addresses', 'addresses.id', '=', 'customers.address_id')
            ->where('products.family_id', $family->id)
            ->where('products.is_for_sale', true)
            ->orderBy('latest_tx.submitted_at', 'desc')
            ->limit(15)
            ->select([
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.unit_price',
                'latest_tx.submitted_at',
                'latest_tx.id as transaction_id',
                'latest_tx.net_amount',
                'customers.contact_name as customer_contact_name',
                'customers.contact_name_components',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'addresses.country_code as customer_country_code',
                'webpages.canonical_url',
                'webpages.url as webpage_url',
            ])
            ->get();

        $formattedProducts = $orderedProducts->map(function ($product) {
            return [
                'id' => $product->id,
                'slug' => $product->slug,
                'code' => $product->code,
                'name' => $product->name,
                'unit_price' => $product->unit_price,
                'ordered_date' => $product->submitted_at,
                'transaction_id' => $product->transaction_id,
                'net_amount' => $product->net_amount,
                'customer' => [
                    'name' => $product->customer_name,
                    'slug' => $product->customer_slug,
                    'contact_name' => $product->customer_contact_name,
                    'contact_name_components' => $product->contact_name_components,
                    'country_code' => $product->customer_country_code,
                ],
                'webpage' => [
                    'canonical_url' => $product->canonical_url,
                    'url' => $product->webpage_url,
                ],
            ];
        })->toArray();

        $existingRecord = FamilyHasProductOrdered::where('family_id', $family->id)->first();

        if ($existingRecord) {
            $existingRecord->update([
                'product' => $formattedProducts,
                'updated_at' => now(),
            ]);
        } else {
            FamilyHasProductOrdered::create([
                'family_id' => $family->id,
                'product' => $formattedProducts,
            ]);
        }
    }
}
