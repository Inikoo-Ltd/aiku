<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappSubscriber;

use App\Actions\CRM\CustomerComms\UpdateCustomerComms;
use App\Models\Chat\MetaChatSession;
use App\Models\Comms\WhatsappSubscriber;
use App\Models\CRM\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UnsubscribeWhatsappNumberFromMarketing
{
    use AsAction;

    public const array STOP_KEYWORDS = ['STOP', 'UNSUBSCRIBE', 'STOP PROMOTIONS'];

    public static function isStopRequest(?string $text): bool
    {
        $keyword = mb_strtoupper(trim(preg_replace('/[\s.!]+/u', ' ', (string)$text)));

        return in_array($keyword, self::STOP_KEYWORDS, true);
    }

    public function handle(MetaChatSession $metaChatSession, string $digits): int
    {
        return DB::transaction(function () use ($metaChatSession, $digits) {
            $unsubscribedCount = 0;

            foreach ($this->getCustomers($metaChatSession, $digits) as $customer) {
                if (!$customer->comms?->is_subscribed_to_whatsapp_newsletter) {
                    continue;
                }

                $unsubscribedCount++;

                UpdateCustomerComms::run($customer->comms, [
                    'is_subscribed_to_whatsapp_newsletter'          => false,
                    'whatsapp_newsletter_unsubscribed_author_type'  => 'Customer',
                    'whatsapp_newsletter_unsubscribed_author_id'    => $customer->id,
                    'whatsapp_newsletter_unsubscribed_origin_type'  => 'MetaChatSession',
                    'whatsapp_newsletter_unsubscribed_origin_id'    => $metaChatSession->id,
                ]);
            }

            $guestSubscribers = WhatsappSubscriber::where('parent_type', 'MetaChatSession')
                ->whereIn('parent_id', $this->getSessionIdsForNumber($metaChatSession, $digits))
                ->get();

            $guestSubscribers->each->delete();

            return $unsubscribedCount + $guestSubscribers->count();
        });
    }

    /**
     * @return Collection<int, Customer>
     */
    protected function getCustomers(MetaChatSession $metaChatSession, string $digits): Collection
    {
        if ($digits === '') {
            return Customer::whereKey($metaChatSession->customer_id)->with('comms')->get();
        }

        return Customer::where('shop_id', $metaChatSession->shop_id)
            ->where(function ($query) use ($metaChatSession, $digits) {
                $query->where('marketing_whatsapp_phone', '+'.$digits)
                    ->orWhere(function ($query) use ($digits) {
                        $query->whereNull('marketing_whatsapp_phone')
                            ->where(function ($query) use ($digits) {
                                $query->where('phone', '+'.$digits)
                                    ->orWhereRaw("regexp_replace(phone, '\\D', '', 'g') = ?", [$digits]);
                            });
                    });

                if ($metaChatSession->customer_id) {
                    $query->orWhere('id', $metaChatSession->customer_id);
                }
            })
            ->with('comms')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    protected function getSessionIdsForNumber(MetaChatSession $metaChatSession, string $digits): array
    {
        if ($digits === '') {
            return [$metaChatSession->id];
        }

        return MetaChatSession::withTrashed()
            ->where('shop_id', $metaChatSession->shop_id)
            ->whereIn('phone_number', ['+'.$digits, $digits])
            ->pluck('id')
            ->push($metaChatSession->id)
            ->unique()
            ->values()
            ->all();
    }
}
