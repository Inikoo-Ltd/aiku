<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 20 Nov 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"

type Band = "high" | "medium" | "low" | null
type Week = { start: string; decided: number; rate: number | null; band: Band; url: string }
type BandShop = { code: string; rate: number | null; decided: number; url: string }

const props = defineProps<{
    title: string
    pageHead: object
    summary: {
        target: number
        medium: number
        days: number
        decided: number
        adopted: number
        bands: { high: BandShop[]; medium: BandShop[]; low: BandShop[]; few: BandShop[] }
        weeks: { start: string; decided: number; adopted: number }[]
    }
    shops: object
    users: object | null
}>()

const BAND_CELL: Record<string, string> = {
    high: "bg-green-50 text-green-800",
    medium: "bg-amber-50 text-amber-800",
    low: "bg-red-50 text-red-800",
}

const BAND_BADGE: Record<string, string> = {
    high: "bg-green-50 text-green-700 ring-green-200",
    medium: "bg-amber-50 text-amber-700 ring-amber-200",
    low: "bg-red-50 text-red-700 ring-red-200",
}

const bandLabel = (band: Band) => (band === "high" ? ctrans("High") : band === "medium" ? ctrans("Medium") : band === "low" ? ctrans("Low") : ctrans("Too few"))

const groupRate = computed(() => (props.summary.decided ? Math.round((100 * props.summary.adopted) / props.summary.decided) : null))

const weeks = computed(() =>
    props.summary.weeks.map((week) => ({
        ...week,
        rate: week.decided ? Math.round((100 * week.adopted) / week.decided) : null,
        label: new Date(week.start).toLocaleDateString(undefined, { day: "numeric", month: "short" }),
    }))
)

const bandRows = computed(() =>
    (["high", "medium", "low", "few"] as const).map((band) => {
        const shops = props.summary.bands[band]
        return {
            band: band === "few" ? null : band,
            shops,
            decided: shops.reduce((total, shop) => total + shop.decided, 0),
        }
    })
)

const shareOfDrafts = (decided: number) => (props.summary.decided ? Math.round((100 * decided) / props.summary.decided) + "%" : "–")
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-6 p-4 text-sm text-gray-700">
        <p class="text-gray-600">
            {{ ctrans("How much of the reply suggestions staff take: sent as they were, with gaps filled or edited. Rewritten, ignored for an own reply, or discarded is not taken. Only drafts with an outcome count.") }}
        </p>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-500">{{ ctrans("Taken, last :days days", { days: summary.days }) }}</div>
                <div class="mt-1 text-3xl font-semibold tabular-nums" :class="groupRate !== null && groupRate >= summary.target ? 'text-green-700' : 'text-gray-900'">
                    {{ groupRate === null ? "–" : groupRate + "%" }}
                </div>
                <div class="mt-1 text-xs tabular-nums text-gray-500">
                    {{ ctrans(":adopted of :decided drafts · target :target% for every shop", { adopted: summary.adopted.toLocaleString(), decided: summary.decided.toLocaleString(), target: summary.target }) }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm lg:col-span-2">
                <div class="text-xs text-gray-500">{{ ctrans("Week by week, all shops") }}</div>
                <div class="relative mt-2 h-28">
                    <div class="absolute inset-x-0 border-t border-dashed border-green-600" :style="{ bottom: summary.target + '%' }">
                        <span class="absolute -top-4 right-0 text-[10px] text-green-700">{{ ctrans("target :target%", { target: summary.target }) }}</span>
                    </div>
                    <div class="flex h-full items-end gap-2">
                        <div v-for="week in weeks" :key="week.start" class="flex h-full flex-1 flex-col justify-end" v-tooltip="ctrans(':adopted of :decided taken', { adopted: week.adopted, decided: week.decided })">
                            <div class="text-center text-[11px] tabular-nums text-gray-600">{{ week.rate === null ? "–" : week.rate + "%" }}</div>
                            <div class="rounded-t bg-gray-400" :style="{ height: (week.rate ?? 0) + '%' }" />
                        </div>
                    </div>
                </div>
                <div class="mt-1 flex gap-2">
                    <div v-for="week in weeks" :key="week.start" class="flex-1 whitespace-nowrap text-center text-[11px] text-gray-500">{{ week.label }}</div>
                </div>
            </div>
        </div>

        <table class="w-full rounded-xl border border-gray-200 bg-white text-sm shadow-sm">
            <thead class="whitespace-nowrap border-b border-gray-200 text-left text-xs text-gray-500">
                <tr>
                    <th class="w-28 px-4 py-2 font-medium">{{ ctrans("Band") }}</th>
                    <th class="w-32 px-4 py-2 text-right font-medium">{{ ctrans("Share of drafts") }}</th>
                    <th class="px-4 py-2 font-medium">{{ ctrans("Shops, taken in the last :days days", { days: summary.days }) }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="row in bandRows" :key="row.band ?? 'few'">
                    <td class="px-4 py-2 align-top">
                        <Link :href="route('grp.chat.ai.aiku_drafts', { filter: { band: row.band ?? 'few', kind: 'draft' } })" class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs ring-1 ring-inset hover:underline" :class="row.band ? BAND_BADGE[row.band] : 'bg-gray-50 text-gray-500 ring-gray-200'">{{ bandLabel(row.band) }}</Link>
                    </td>
                    <td class="px-4 py-2 text-right align-top tabular-nums">{{ shareOfDrafts(row.decided) }}</td>
                    <td class="px-4 py-2">
                        <div class="flex flex-wrap gap-1.5">
                            <Link v-for="shop in row.shops" :key="shop.code" :href="shop.url" class="inline-grid grid-cols-[3.5rem_2.5rem] whitespace-nowrap rounded border border-gray-200 px-1.5 py-0.5 text-xs tabular-nums hover:bg-gray-50">
                                <span class="font-medium text-gray-800">{{ shop.code }}</span>
                                <span class="text-right text-gray-600">{{ shop.rate === null ? "–" : shop.rate + "%" }}</span>
                            </Link>
                            <span v-if="!row.shops.length" class="text-xs text-gray-400">{{ ctrans("None") }}</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2 class="text-base font-semibold text-gray-900">{{ ctrans("Per shop") }}</h2>
    </div>

    <Table :resource="shops" name="shops">
        <template v-for="column in ['rewritten', 'ignored', 'discarded']" :key="column" #[`cell(${column})`]="{ item }">
            <Link v-if="item[column]" :href="item.urls[column]" class="tabular-nums text-gray-700 hover:underline">{{ item[column] }}</Link>
            <span v-else class="tabular-nums text-gray-400">0</span>
        </template>
        <template #cell(shop_name)="{ item }">
            <Link :href="item.url" class="font-medium text-gray-700 hover:underline" v-tooltip="ctrans('See its suggestions')">{{ item.shop_name }}</Link>
            <span class="ml-1.5 whitespace-nowrap text-xs text-gray-400">{{ item.shop_code }}</span>
        </template>
        <template #cell(band)="{ item }">
            <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs ring-1 ring-inset" :class="item.band ? BAND_BADGE[item.band] : 'bg-gray-50 text-gray-500 ring-gray-200'">{{ bandLabel(item.band) }}</span>
        </template>
        <template #cell(rate)="{ item }">
            <span class="whitespace-nowrap font-medium tabular-nums">{{ item.rate === null ? "–" : item.rate + "%" }}</span>
        </template>
        <template #cell(weeks)="{ item }">
            <div class="grid grid-cols-[repeat(8,2.75rem)] gap-1">
                <Link v-for="week in item.weeks" :key="week.start" :href="week.url"
                    class="rounded px-1 py-0.5 text-center text-xs tabular-nums hover:ring-1 hover:ring-gray-300"
                    :class="week.band ? BAND_CELL[week.band] : 'text-gray-400'"
                    v-tooltip="ctrans('Week of :date: :decided drafts', { date: week.start, decided: week.decided })">
                    {{ week.rate === null ? "–" : week.rate + "%" }}
                </Link>
            </div>
        </template>
    </Table>

    <template v-if="users">
        <div class="px-4 pt-8">
            <h2 class="text-base font-semibold text-gray-900">{{ ctrans("Per person") }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ ctrans("Only on the shops you supervise, and only shown to their supervisors and admins. The person is who used, edited or discarded the draft, or answered beside it.") }}</p>
        </div>

        <Table :resource="users" name="users" class="mt-3">
            <template v-for="column in ['rewritten', 'ignored', 'discarded']" :key="column" #[`cell(${column})`]="{ item }">
                <Link v-if="item[column]" :href="item.urls[column]" class="tabular-nums text-gray-700 hover:underline">{{ item[column] }}</Link>
                <span v-else class="tabular-nums text-gray-400">0</span>
            </template>
            <template #cell(user_name)="{ item }">
                <Link :href="item.url" class="font-medium text-gray-700 hover:underline" v-tooltip="ctrans('See the suggestions they decided')">{{ item.user_name }}</Link>
            </template>
            <template #cell(shops)="{ item }">
                <span class="whitespace-nowrap text-xs text-gray-500">{{ item.shops }}</span>
            </template>
            <template #cell(band)="{ item }">
                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs ring-1 ring-inset" :class="item.band ? BAND_BADGE[item.band] : 'bg-gray-50 text-gray-500 ring-gray-200'">{{ bandLabel(item.band) }}</span>
            </template>
            <template #cell(rate)="{ item }">
                <span class="whitespace-nowrap font-medium tabular-nums">{{ item.rate === null ? "–" : item.rate + "%" }}</span>
            </template>
            <template #cell(weeks)="{ item }">
                <div class="grid grid-cols-[repeat(8,2.75rem)] gap-1">
                    <Link v-for="week in item.weeks" :key="week.start" :href="week.url"
                        class="rounded px-1 py-0.5 text-center text-xs tabular-nums hover:ring-1 hover:ring-gray-300"
                        :class="week.band ? BAND_CELL[week.band] : 'text-gray-400'"
                        v-tooltip="ctrans('Week of :date: :decided drafts', { date: week.start, decided: week.decided })">
                        {{ week.rate === null ? "–" : week.rate + "%" }}
                    </Link>
                </div>
            </template>
        </Table>
    </template>
</template>
