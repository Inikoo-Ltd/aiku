<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 12:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Actions\Helpers\SendDiscordDirectMessage;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * INI-214: a silent outage (checkout, payments, website, a sync) throws nothing for Sentry to see; the
 * first sign is many customers writing in at once, and at weekends nobody is reading. This counts the
 * chats customers wrote in per shop over the last half hour and DMs an engineer when it is far above
 * that shop's usual level for the same weekday and time over the previous four weeks. No AI involved.
 */
class AlertChatMessageSpikes
{
    use AsAction;

    public const int WINDOW_MINUTES = 30;
    public const int BASELINE_WEEKS = 4;
    /** Busiest shops reach 8-9 chats in a half hour at their p99 (prod, Sep-Nov 2026). */
    public const int MIN_CHATS = 12;
    public const int SPIKE_FACTOR = 3;
    public const int COOLDOWN_HOURS = 3;
    public const int SAMPLE_MESSAGES = 5;

    public string $commandSignature = 'chat:alert-spikes {--to=raul : aiku username to DM} {--r|dry-run : Report spikes, send nothing}';

    public string $commandDescription = 'DM an engineer when many more customers than usual write in to a shop at once';

    /**
     * @return array<int, array{shop_id: int, chats: int, usual: float, sent: bool}>
     */
    public function handle(string $to = 'raul', bool $dryRun = false, ?Command $command = null): array
    {
        $now     = now();
        $current = $this->chatsPerShop($now);

        $baselines = collect(range(1, self::BASELINE_WEEKS))
            ->map(fn (int $week) => $this->chatsPerShop($now->copy()->subWeeks($week)));

        $spikes = [];
        foreach ($current as $shopId => $chats) {
            $usual = $baselines->sum(fn (array $week) => $week[$shopId] ?? 0) / self::BASELINE_WEEKS;

            if ($chats < max(self::MIN_CHATS, self::SPIKE_FACTOR * $usual)) {
                continue;
            }

            $spike = ['shop_id' => $shopId, 'chats' => $chats, 'usual' => $usual, 'sent' => false];

            if (!$dryRun && !Cache::has($this->cooldownKey($shopId))) {
                $spike['sent'] = $this->alert($to, $spike, $now, $command);
            }

            $command?->warn(sprintf('Shop %d: %d chats in %d min, usually %.1f%s', $shopId, $chats, self::WINDOW_MINUTES, $usual, $spike['sent'] ? ' (alerted)' : ''));
            $spikes[] = $spike;
        }

        if (!$spikes) {
            $command?->info('No shop has an unusual number of customers writing in');
        }

        return $spikes;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $this->handle($command->option('to'), (bool) $command->option('dry-run'), $command);

        return 0;
    }

    /**
     * Distinct conversations a customer wrote in, so one customer sending ten messages counts once.
     *
     * @return array<int, int> shop id => chats
     */
    protected function chatsPerShop(Carbon $until): array
    {
        $from = $until->copy()->subMinutes(self::WINDOW_MINUTES);

        $chats = DB::table('chat_messages')
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.chat_session_id')
            ->whereIn('chat_messages.sender_type', $this->customerSenderTypes())
            ->where('chat_sessions.is_spam', false)
            ->whereNotNull('chat_sessions.shop_id')
            ->where('chat_messages.created_at', '>', $from)
            ->where('chat_messages.created_at', '<=', $until)
            ->groupBy('chat_sessions.shop_id')
            ->selectRaw('chat_sessions.shop_id, count(distinct chat_messages.chat_session_id) as chats')
            ->pluck('chats', 'shop_id');

        $whatsapp = DB::table('meta_chat_messages')
            ->join('meta_chat_sessions', 'meta_chat_sessions.id', '=', 'meta_chat_messages.meta_chat_session_id')
            ->whereIn('meta_chat_messages.sender_type', $this->customerSenderTypes())
            ->where('meta_chat_sessions.is_spam', false)
            ->whereNotNull('meta_chat_sessions.shop_id')
            ->where('meta_chat_messages.created_at', '>', $from)
            ->where('meta_chat_messages.created_at', '<=', $until)
            ->groupBy('meta_chat_sessions.shop_id')
            ->selectRaw('meta_chat_sessions.shop_id, count(distinct meta_chat_messages.meta_chat_session_id) as chats')
            ->pluck('chats', 'shop_id');

        $perShop = [];
        foreach ([$chats, $whatsapp] as $counts) {
            foreach ($counts as $shopId => $count) {
                $perShop[$shopId] = ($perShop[$shopId] ?? 0) + (int) $count;
            }
        }

        return $perShop;
    }

    /**
     * @param array{shop_id: int, chats: int, usual: float} $spike
     */
    protected function alert(string $to, array $spike, Carbon $now, ?Command $command): bool
    {
        $recipient = User::where('username', $to)->first();
        if (!$recipient) {
            $command?->error("No aiku user $to to alert");

            return false;
        }

        $shop  = Shop::find($spike['shop_id']);
        $lines = [
            '🚨 **Many customers are writing in at once**',
            sprintf(
                '**%s**: %d chats in the last %d minutes, usually %s at this time on a %s',
                $shop?->name ?? 'Shop '.$spike['shop_id'],
                $spike['chats'],
                self::WINDOW_MINUTES,
                rtrim(rtrim(number_format($spike['usual'], 1), '0'), '.'),
                $now->englishDayOfWeek
            ),
            'Something may be broken (checkout, payments, website, a sync). Newest messages:',
            ...$this->newestMessages($spike['shop_id'], $now)->map(fn (string $text) => '• '.$text),
        ];

        try {
            SendDiscordDirectMessage::run($recipient, implode("\n", $lines));
        } catch (RuntimeException $e) {
            $command?->error($e->getMessage());

            return false;
        }

        Cache::put($this->cooldownKey($spike['shop_id']), true, now()->addHours(self::COOLDOWN_HOURS));

        return true;
    }

    /**
     * @return Collection<int, string>
     */
    protected function newestMessages(int $shopId, Carbon $now): Collection
    {
        $from = $now->copy()->subMinutes(self::WINDOW_MINUTES);

        $chats = DB::table('chat_messages')
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.chat_session_id')
            ->where('chat_sessions.shop_id', $shopId)
            ->where('chat_sessions.is_spam', false)
            ->whereIn('chat_messages.sender_type', $this->customerSenderTypes())
            ->where('chat_messages.created_at', '>', $from)
            ->orderByDesc('chat_messages.created_at')
            ->limit(self::SAMPLE_MESSAGES)
            ->get(['chat_messages.created_at', 'chat_messages.message_text', 'chat_sessions.channel', 'chat_sessions.metadata']);

        $whatsapp = DB::table('meta_chat_messages')
            ->join('meta_chat_sessions', 'meta_chat_sessions.id', '=', 'meta_chat_messages.meta_chat_session_id')
            ->where('meta_chat_sessions.shop_id', $shopId)
            ->where('meta_chat_sessions.is_spam', false)
            ->whereIn('meta_chat_messages.sender_type', $this->customerSenderTypes())
            ->where('meta_chat_messages.created_at', '>', $from)
            ->orderByDesc('meta_chat_messages.created_at')
            ->limit(self::SAMPLE_MESSAGES)
            ->get(['meta_chat_messages.created_at', 'meta_chat_messages.message_text', DB::raw("'whatsapp' as channel"), DB::raw('null as metadata')]);

        return $chats->concat($whatsapp)
            ->sortByDesc('created_at')
            ->take(self::SAMPLE_MESSAGES)
            ->map(function (object $message) {
                $subject = Arr::get(json_decode($message->metadata ?? '[]', true) ?: [], 'email_subject');

                return $message->channel.': '.Str::limit(Str::squish($subject ?: (string) $message->message_text), 100);
            })
            ->values();
    }

    /**
     * @return array<int, string>
     */
    protected function customerSenderTypes(): array
    {
        return [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value];
    }

    protected function cooldownKey(int $shopId): string
    {
        return 'chat:spike-alerted:'.$shopId;
    }
}
