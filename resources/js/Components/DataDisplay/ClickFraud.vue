<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ctrans } from '@/Composables/useTrans'

defineProps<{
    data: {
        period_label: string
        from: string | null
        totals: {
            clicks: number
            bots: number
            bot_pct: number | null
            ips: number
            repeats: number
        }
        channels: {
            channel: string
            clicks: number
            bots: number
            bot_pct: number | null
        }[]
        suspect_ips: {
            ip: string
            country: string | null
            clicks: number
            bots: number
            channels: string
            device: string | null
            first_seen: string
            last_seen: string
        }[]
        recent_bots: {
            at: string
            channel: string
            campaign_ref: string | null
            ip: string | null
            country: string | null
            device: string | null
            url: string | null
        }[]
    }
}>()

const time = (value: string) => new Date(value).toLocaleString(undefined, {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
})
</script>

<template>
    <div class="px-4 py-4 space-y-3">
        <div class="text-xs text-gray-500">
            {{ ctrans('Period') }}: {{ data.period_label }} ·
            {{ ctrans('Clicks are recorded since 8 Aug 2026 and kept 90 days. Suspicious means worth a look, not fraud proven.') }}
        </div>

        <div class="border border-gray-200 rounded-md px-4 py-3">
            <div class="flex items-center gap-x-6 text-sm tabular-nums">
                <span>{{ data.totals.clicks }} {{ ctrans('clicks') }}</span>
                <span>{{ data.totals.ips }} {{ ctrans('IPs') }}</span>
                <span>{{ data.totals.repeats }} {{ ctrans('repeats') }}</span>
                <span :class="data.totals.bots > 0 ? 'text-red-600' : 'text-gray-500'">
                    {{ data.totals.bots }} {{ ctrans('bot clicks') }}
                    <template v-if="data.totals.bot_pct !== null">({{ data.totals.bot_pct }}%)</template>
                </span>
            </div>
        </div>

        <div class="border border-gray-200 rounded-md px-4 py-3">
            <div class="text-sm">{{ ctrans('By channel') }}</div>
            <table class="mt-2 text-xs text-gray-600 min-w-72">
                <thead>
                    <tr class="text-gray-400">
                        <th class="text-left font-normal pr-4">{{ ctrans('Channel') }}</th>
                        <th class="text-right font-normal px-2">{{ ctrans('Clicks') }}</th>
                        <th class="text-right font-normal px-2">{{ ctrans('Bots') }}</th>
                        <th class="text-right font-normal pl-2">{{ ctrans('Bot share') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in data.channels" :key="row.channel">
                        <td class="pr-4 py-0.5">{{ row.channel }}</td>
                        <td class="text-right px-2 tabular-nums">{{ row.clicks }}</td>
                        <td class="text-right px-2 tabular-nums" :class="row.bots > 0 ? 'text-red-600' : ''">{{ row.bots }}</td>
                        <td class="text-right pl-2 tabular-nums">{{ row.bot_pct !== null ? row.bot_pct + '%' : '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="border border-gray-200 rounded-md px-4 py-3">
            <div class="text-sm">{{ ctrans('Suspicious IPs') }}</div>
            <div class="mt-1 text-xs text-gray-500">
                {{ ctrans('Five or more clicks from one address in the period, or any click whose browser identified as a bot.') }}
            </div>
            <div v-if="!data.suspect_ips.length" class="mt-2 text-xs text-gray-400">{{ ctrans('None in this period') }}</div>
            <table v-else class="mt-2 text-xs text-gray-600 w-full">
                <thead>
                    <tr class="text-gray-400">
                        <th class="text-left font-normal pr-4">{{ ctrans('IP') }}</th>
                        <th class="text-left font-normal px-2">{{ ctrans('Country') }}</th>
                        <th class="text-right font-normal px-2">{{ ctrans('Clicks') }}</th>
                        <th class="text-right font-normal px-2">{{ ctrans('Bots') }}</th>
                        <th class="text-left font-normal px-2">{{ ctrans('Channels') }}</th>
                        <th class="text-left font-normal px-2">{{ ctrans('Device') }}</th>
                        <th class="text-left font-normal pl-2">{{ ctrans('First / last seen') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in data.suspect_ips" :key="row.ip">
                        <td class="pr-4 py-0.5 font-mono">{{ row.ip }}</td>
                        <td class="px-2">{{ row.country ?? '—' }}</td>
                        <td class="text-right px-2 tabular-nums">{{ row.clicks }}</td>
                        <td class="text-right px-2 tabular-nums" :class="row.bots > 0 ? 'text-red-600' : ''">{{ row.bots }}</td>
                        <td class="px-2">{{ row.channels }}</td>
                        <td class="px-2">{{ row.device ?? '—' }}</td>
                        <td class="pl-2 tabular-nums">{{ time(row.first_seen) }} / {{ time(row.last_seen) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="border border-gray-200 rounded-md px-4 py-3">
            <div class="text-sm">{{ ctrans('Latest bot clicks') }}</div>
            <div v-if="!data.recent_bots.length" class="mt-2 text-xs text-gray-400">{{ ctrans('None in this period') }}</div>
            <table v-else class="mt-2 text-xs text-gray-600 w-full">
                <thead>
                    <tr class="text-gray-400">
                        <th class="text-left font-normal pr-4">{{ ctrans('When') }}</th>
                        <th class="text-left font-normal px-2">{{ ctrans('Channel') }}</th>
                        <th class="text-left font-normal px-2">{{ ctrans('Campaign') }}</th>
                        <th class="text-left font-normal px-2">{{ ctrans('IP') }}</th>
                        <th class="text-left font-normal px-2">{{ ctrans('Country') }}</th>
                        <th class="text-left font-normal pl-2">{{ ctrans('Landing page') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in data.recent_bots" :key="index">
                        <td class="pr-4 py-0.5 tabular-nums">{{ time(row.at) }}</td>
                        <td class="px-2">{{ row.channel }}</td>
                        <td class="px-2">{{ row.campaign_ref ?? '—' }}</td>
                        <td class="px-2 font-mono">{{ row.ip ?? '—' }}</td>
                        <td class="px-2">{{ row.country ?? '—' }}</td>
                        <td class="pl-2 truncate max-w-64">{{ row.url ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
