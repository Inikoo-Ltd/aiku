<?php

namespace App\Actions\CRM\AppointmentBlock;

use App\Models\Catalogue\Shop;
use App\Models\CRM\AppointmentBlock;
use App\Services\GoogleCalendar\GoogleCalendarClient;
use App\Services\GoogleCalendar\ReleaseWhenGoogleCalendarRateLimited;
use Illuminate\Http\Client\RequestException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Blocked time is written into the shop calendar as a busy event, and removed with the block.
 */
class SyncAppointmentBlockGoogleCalendarEvent
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public int $jobTries = 10;

    public int $jobMaxExceptions = 3;

    public function getJobMiddleware(): array
    {
        return [new ReleaseWhenGoogleCalendarRateLimited()];
    }

    public static function dispatchFor(Shop $shop, int $blockId): void
    {
        if (GoogleCalendarClient::isConnected($shop)) {
            static::dispatch($shop->id, $blockId);
        }
    }

    /**
     * @throws RequestException
     */
    public function handle(int $shopId, int $blockId): void
    {
        $shop   = Shop::find($shopId);
        $client = $shop ? GoogleCalendarClient::forShop($shop) : null;

        if (!$client) {
            return;
        }

        $block = AppointmentBlock::where('shop_id', $shopId)->with(['shop', 'organisation', 'appointmentType'])->find($blockId);

        if (!$block) {
            $client->removeEvent(GoogleCalendarClient::blockEventId($blockId));

            return;
        }

        $client->putEvent(GoogleCalendarClient::blockEventId($blockId), $this->event($block));
    }

    public function event(AppointmentBlock $block): array
    {
        $timezone = $block->shop->timezone?->name ?? 'UTC';

        return [
            'summary'            => __('Blocked').($block->appointmentType ? ' · '.$block->appointmentType->name : '').($block->reason ? ': '.$block->reason : ''),
            'description'        => implode("\n", [
                $block->appointmentType
                    ? __('No :type appointments can be booked in this time.', ['type' => $block->appointmentType->name])
                    : __('No appointments can be booked in this time.'),
                route('grp.org.shops.show.crm.appointments.calendar', [$block->organisation->slug, $block->shop->slug]),
            ]),
            'status'             => 'confirmed',
            'transparency'       => 'opaque',
            'start'              => ['dateTime' => $block->starts_at->copy()->setTimezone($timezone)->toIso8601String(), 'timeZone' => $timezone],
            'end'                => ['dateTime' => $block->ends_at->copy()->setTimezone($timezone)->toIso8601String(), 'timeZone' => $timezone],
            'extendedProperties' => ['private' => [GoogleCalendarClient::BLOCK_EVENT_PROPERTY => (string) $block->id]],
        ];
    }
}
