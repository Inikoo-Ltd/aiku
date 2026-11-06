<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 06 Nov 2026 17:03:21 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import PrimeImage from "primevue/image"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faExternalLink)

defineProps<{
    data: object
    tab?: string
    masterShopSlug: string
    currencyCode: string
}>()

const locale = useLocaleStore()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(checked_at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.checked_at) }}</span>
        </template>
        <template #cell(master_asset_code)="{ item }">
            <Link :href="route('grp.masters.master_shops.show.master_products.show', { masterShop: masterShopSlug, masterProduct: item.master_asset_slug })" class="whitespace-nowrap font-medium text-gray-700 hover:underline">
                {{ item.master_asset_code }}
            </Link>
            <div class="text-xs text-gray-500">{{ item.master_asset_name }}</div>
        </template>
        <template #cell(screenshot)="{ item }">
            <PrimeImage v-if="item.screenshot" :src="item.screenshot_thumbnail?.original" :previewSrc="item.screenshot.original" :alt="ctrans('Their listing on :date', { date: useFormatTime(item.checked_at) })" preview imageClass="h-12 w-20 rounded border border-gray-200 object-cover object-top cursor-zoom-in" />
            <span v-else class="text-xs text-gray-400">{{ ctrans("No screenshot") }}</span>
        </template>
        <template #cell(competitor_product_name)="{ item }">
            <a v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-700 hover:underline">
                {{ item.competitor_product_name }}
                <FontAwesomeIcon icon="fal fa-external-link" class="text-xs" fixed-width />
            </a>
            <span v-else>{{ item.competitor_product_name }}</span>
        </template>
        <template #cell(price)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(currencyCode, item.price) }}</span>
            <div v-if="item.units > 1" class="text-xs text-gray-500">{{ ctrans(":n units", { n: locale.number(item.units) }) }}</div>
        </template>
        <template #cell(their_unit_price)="{ item }">
            <span v-if="item.their_unit_price !== null" class="tabular-nums">{{ locale.currencyFormat(item.unit_currency_code, item.their_unit_price) }}</span>
        </template>
        <template #cell(our_unit_price)="{ item }">
            <span v-if="item.our_unit_price !== null" class="tabular-nums">{{ locale.currencyFormat(item.unit_currency_code, item.our_unit_price) }}</span>
        </template>
        <template #cell(difference_pct)="{ item }">
            <span v-if="item.difference_pct !== null" class="tabular-nums" :class="item.difference_pct < 0 ? 'text-red-600' : 'text-green-600'"
                v-tooltip="item.difference_pct < 0 ? ctrans('They are cheaper') : ctrans('They are dearer')">
                {{ item.difference_pct > 0 ? "+" : "" }}{{ item.difference_pct }}%
            </span>
        </template>
        <template #cell(change)="{ item }">
            <span v-if="item.change === null" class="text-xs text-gray-400">{{ ctrans("First check") }}</span>
            <span v-else-if="Number(item.change) === 0" class="text-xs text-gray-500">{{ ctrans("Same") }}</span>
            <span v-else class="whitespace-nowrap tabular-nums" :class="item.change < 0 ? 'text-red-600' : 'text-green-600'">
                {{ item.change > 0 ? "+" : "-" }}{{ locale.currencyFormat(item.unit_currency_code, Math.abs(item.change)) }}
            </span>
        </template>
        <template #cell(delivery_days)="{ item }">
            <span v-if="item.delivery_days !== null" class="whitespace-nowrap tabular-nums">{{ ctrans(":n days", { n: item.delivery_days }) }}</span>
        </template>
    </Table>
</template>
