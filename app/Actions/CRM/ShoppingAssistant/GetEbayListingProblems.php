<?php

/*
 * Author: Raul Perusquia
 * Created: Thu, 19 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Dropshipping\Ebay\Product\UpdateEbayPortfolio;
use App\Enums\Dropshipping\CustomerSalesChannelConnectionStatusEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantMessageRoleEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\ShoppingAssistant\ShoppingAssistantConversation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Why the signed-in customer's products do not list on their own eBay channels (INI-391), read
 * from what our uploads stored: each portfolio keeps eBay's last refusal in upload_warning /
 * errors_response until the next upload succeeds. Only this customer's channels, filtered by
 * customer_id in the query (hard rule: never another customer's data). Counts, codes and fixes
 * are lines written here, so the model writes no figure. Causes only we can fix (data eBay needs
 * from our product records, our own upload faults) are returned as ours for report_to_engineers.
 */
class GetEbayListingProblems
{
    use AsObject;

    public const int MAX_CHANNELS = 3;

    public const int MAX_FAILED = 5;

    public const array CUSTOMER_CAUSES = ['selling_limit', 'seller_account', 'business_policy', 'price_below_minimum', 'ebay_policy', 'overseas_block', 'listing_ended', 'ebay_temporary', 'connection'];

    public const array OUR_CAUSES = ['needs_our_data', 'ours'];

    /**
     * @return array{content: array<string, mixed>, products: array{}, error: ?string, fact?: string}
     */
    public function handle(Customer $customer, string $locale, ?ShoppingAssistantConversation $conversation = null): array
    {
        $channels = DB::table('customer_sales_channels')
            ->join('platforms', 'platforms.id', '=', 'customer_sales_channels.platform_id')
            ->where('customer_sales_channels.customer_id', $customer->id)
            ->where('customer_sales_channels.status', CustomerSalesChannelStatusEnum::OPEN->value)
            ->whereNull('customer_sales_channels.deleted_at')
            ->where('platforms.type', PlatformTypeEnum::EBAY->value)
            ->orderByDesc('customer_sales_channels.number_portfolios')
            ->limit(self::MAX_CHANNELS)
            ->get(['customer_sales_channels.id', 'customer_sales_channels.name', 'customer_sales_channels.connection_status', 'customer_sales_channels.settings']);

        if ($channels->isEmpty()) {
            return ['content' => ['has_ebay' => false, 'note' => 'They have no eBay channel with us. Say so; to connect one, call shop_information for the eBay guide.'], 'products' => [], 'error' => null];
        }

        $facts      = [];
        $causes     = [];
        $ourFailed  = [];
        $dataGaps   = [];
        $number     = fn (int $value) => Number::format($value, locale: $locale) ?: (string) $value;

        foreach ($channels as $channel) {
            $name      = filled($channel->name) ? $channel->name : 'eBay';
            $portfolio = fn () => DB::table('portfolios')
                ->where('customer_id', $customer->id)
                ->where('customer_sales_channel_id', $channel->id)
                ->where('status', true);

            if ($channel->connection_status !== CustomerSalesChannelConnectionStatusEnum::CONNECTED->value) {
                $facts[]  = __('eBay ":channel" is not connected: press Reconnect on that channel in Channels, then upload again.', ['channel' => $name], $locale);
                $causes[] = 'connection';
            }

            $listed  = $portfolio()->where('platform_status', true)->count();
            $drafts  = $portfolio()->where('platform_status', false)->where('data->is_platform_draft', true)->count();
            $failed  = $portfolio()->where('platform_status', false)->where(fn ($query) => $query->whereNotNull('upload_warning')->orWhereRaw("errors_response is not null and errors_response::text not in ('{}', '[]', 'null')"));
            $failedCount = (clone $failed)->count();
            $waiting = $portfolio()->where('platform_status', false)->whereNull('platform_product_id')->count();

            $facts[] = __('eBay ":channel": :listed listed, :drafts drafts not published, :failed refused by eBay, :waiting not sent yet.', [
                'channel' => $name,
                'listed'  => $number($listed),
                'drafts'  => $number($drafts),
                'failed'  => $number($failedCount),
                'waiting' => $number(max(0, $waiting - $failedCount)),
            ], $locale);

            if ($drafts > 0) {
                $facts[]  = __('Drafts do not show in eBay Seller Hub until you press Publish on eBay, or Publish drafts, in My Products.', [], $locale)
                    .(Arr::get(json_decode((string) $channel->settings, true) ?: [], 'upload_as_draft') ? ' '.__('Upload as draft is on in Manage Sales Channel, so new uploads stay drafts.', [], $locale) : '');
                $causes[] = 'drafts';
            }

            $rows = $failed->orderByDesc('updated_at')->limit(self::MAX_FAILED)->get(['item_code', 'item_name', 'upload_warning', 'errors_response']);
            foreach ($rows as $row) {
                [$cause, $message] = self::refusalOf($row->upload_warning, $row->errors_response);
                $causes[]          = $cause;

                if ($cause === 'needs_our_data') {
                    $dataGaps[$row->item_code] = $message;
                } elseif ($cause === 'ours') {
                    $ourFailed[] = $row->item_code;
                }

                $facts[] = trim($row->item_code.' · '.$row->item_name).' · '.self::fixOf($cause, $message, $locale);
            }

            if ($failedCount > $rows->count()) {
                $facts[] = __('And :count more.', ['count' => $number($failedCount - $rows->count())], $locale);
            }
        }

        $causes = array_values(array_unique($causes));

        if (in_array('listing_ended', $causes, true)) {
            $facts[] = __('Tip: turn on "Out-of-stock control" in your eBay selling preferences, so eBay keeps your listings when a product runs out instead of ending them.', [], $locale);
        }

        if ($dataGaps && $conversation) {
            StoreShoppingAssistantEngineerTicket::make()->storeCatalogueTask(
                $conversation,
                'eBay refuses products of '.$customer->reference.' for missing product data',
                "eBay refuses these products because it needs information from our product records:\n".collect($dataGaps)->map(fn (string $message, string $code) => "$code: ".Str::limit($message, 200))->implode("\n"),
                (string) $conversation->messages()->where('role', ShoppingAssistantMessageRoleEnum::CUSTOMER)->latest('id')->value('body')
            );
        }

        return [
            'content'  => array_filter([
                'has_ebay'         => true,
                'causes'           => $causes,
                'product_data'     => $dataGaps ? 'Our products team has been asked to add the product information eBay needs; never ask the customer to fix it.' : null,
                'ours_to_fix'      => $ourFailed ? 'These products fail because of a fault on our side: '.implode(', ', array_unique($ourFailed)).'. Call report_to_engineers with tried_fix true and why_only_us naming the product codes and the eBay message; never ask the customer to fix them.' : null,
                'note'             => 'Lines under your reply, written by the system, show each eBay channel with its listed, draft, refused and not yet sent products, and for up to five refused products the cause and what to do. In one or two sentences, with no counts, codes or figures, tell them what is holding their listings back, starting with the cause that blocks the most, and that the lines below say what to do. Never ask them to change what the line says only we can fix.',
            ]),
            'products' => [],
            'error'    => null,
            'fact'     => implode("\n", $facts),
        ];
    }

    /**
     * eBay's own "ended item" verdict from the stock sync is the newest word on a listing, so it wins over an
     * older upload_warning. Otherwise eBay's upload_warning decides, because a failed match stores our generic
     * "not in the listing yet" (translated) in errors_response beside eBay's real refusal (INI-393).
     *
     * @return array{0: string, 1: string} the cause and the message it was read from
     */
    public static function refusalOf(?string $warning, mixed $errors): array
    {
        $decoded = is_string($errors) ? json_decode($errors, true) : $errors;
        $warningErrors = is_string($warning) ? json_decode($warning, true) : null;

        if (is_array($warningErrors)) {
            $warningErrors = array_is_list($warningErrors) ? $warningErrors : [$warningErrors];
            $warning       = self::messageOf(null, $warningErrors);
        }

        if (UpdateEbayPortfolio::isListingEndedError($decoded)) {
            return ['listing_ended', self::messageOf(null, $decoded)];
        }

        if (is_array($warningErrors) && UpdateEbayPortfolio::isListingEndedError($warningErrors)) {
            return ['listing_ended', $warning];
        }

        if (filled($warning) && ($cause = self::causeOf($warning)) !== 'other') {
            return [$cause, trim($warning)];
        }

        $message = self::messageOf($warning, $errors);

        return [self::causeOf($message), $message];
    }

    public static function messageOf(?string $warning, mixed $errors): string
    {
        $decoded = is_string($errors) ? json_decode($errors, true) : $errors;
        $parts   = [$warning];

        if (is_array($decoded)) {
            $parts[] = $decoded['message'] ?? null;
            if (array_is_list($decoded)) {
                foreach ($decoded as $error) {
                    $parts[] = is_array($error) ? ($error['message'] ?? null) : (is_string($error) ? $error : null);
                }
            }
        }

        return trim(implode(' ', array_unique(array_filter($parts, fn ($part) => is_string($part) && trim($part) !== ''))));
    }

    public static function causeOf(string $message): string
    {
        $text = Str::lower($message);

        return match (true) {
            (bool) preg_match('/number of items|amount you can list|reached the amount|reached the number/u', $text)                          => 'selling_limit',
            (bool) preg_match('/seller account|cuenta de vendedor|additional information|verkäuferkonto/u', $text)                             => 'seller_account',
            (bool) preg_match('/fulfil+ment policy|postage service|return policy|payment policy/u', $text)                                    => 'business_policy',
            (bool) preg_match('/minimum price|price in the listing/u', $text)                                                                 => 'price_below_minimum',
            (bool) preg_match('/improper words|violation of ebay/u', $text)                                                                    => 'ebay_policy',
            (bool) preg_match('/overseas warehouse|different from your registered|whappeals/u', $text)                                         => 'overseas_block',
            (bool) preg_match('/ended item|offer is not available|not in the listing yet/u', $text)                                            => 'listing_ended',
            (bool) preg_match('/too many requests|system error|internal server|internal error|unable to process|timed out|time-out/u', $text) => 'ebay_temporary',
            (bool) preg_match('/item specific|aspects|isbn|está vacío|condition|category|item\.country|description/u', $text)                 => 'needs_our_data',
            (bool) preg_match('/offer entity already exists|product not found|availability not found|\bsku\b/u', $text)                       => 'ours',
            (bool) preg_match('/token|authori[sz]|expired/u', $text)                                                                           => 'connection',
            default                                                                                                                            => 'other',
        };
    }

    public static function fixOf(string $cause, string $message, string $locale): string
    {
        return match ($cause) {
            'selling_limit'       => __('Your eBay selling limit for this month is reached: upload fewer or cheaper products, ask eBay to raise your limits, or wait for next month.', [], $locale),
            'seller_account'      => __('eBay has not finished setting up your seller account: sign in to eBay, finish the registration, then upload again.', [], $locale),
            'business_policy'     => __('One of your eBay business policies (postage, returns or payment) is not valid for this item: fix it in eBay, then upload again.', [], $locale),
            'price_below_minimum' => __('The price is below the minimum eBay accepts: set a higher price in Edit Product, then publish.', [], $locale),
            'ebay_policy'         => __('eBay refused it under its own rules: read the messages in your eBay account, only eBay can review this.', [], $locale),
            'overseas_block'      => __('eBay blocks goods stored in another country for your account: ask eBay Customer Support for approval.', [], $locale),
            'listing_ended'       => __('The listing no longer exists on eBay: press Create new product to list it again.', [], $locale),
            'ebay_temporary'      => __('eBay had a problem on its side, nothing is wrong with the product: try again later.', [], $locale),
            'connection'          => __('The connection to eBay has expired: press Reconnect on the channel, then upload again.', [], $locale),
            'needs_our_data'      => __('eBay needs information about this product that only we can add: we are looking at it.', [], $locale),
            'ours'                => __('This one fails because of a problem on our side, not yours: our team needs to look at it.', [], $locale),
            default               => __('eBay said: ":message"', ['message' => Str::limit($message, 200)], $locale),
        };
    }
}
