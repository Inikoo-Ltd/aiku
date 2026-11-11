<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle, faExclamationTriangle, faLock, faCircle, faShoppingBag, faStore, faIndustry } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(faCheckCircle, faExclamationTriangle, faLock, faCircle, faShoppingBag, faStore, faIndustry)

defineProps<{
    data: object
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

const sellsToIcons: Record<string, string> = {
    consumer: "fal fa-shopping-bag",
    wholesale: "fal fa-store",
    factory: "fal fa-industry",
}

const statusLabels: Record<string, string> = {
    ok: ctrans("Read fine last time"),
    login_failed: ctrans("Could not log in, check the email and password"),
    blocked: ctrans("The website blocked us"),
    search_broken: ctrans("The search link does not search, check it on Edit"),
    error: ctrans("Could not open the website"),
}

const readMethodLabels: Record<string, string> = {
    shopify: ctrans("Shopify catalogue"),
    woocommerce: ctrans("WooCommerce catalogue"),
    feed: ctrans("Product feed"),
    search: ctrans("Website search"),
    hand: ctrans("Checked by hand"),
    none: ctrans("Nothing yet"),
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(status)="{ item }">
            <FontAwesomeIcon v-if="item.status === 'ok'" icon="fal fa-check-circle" class="text-green-500" v-tooltip="statusLabels.ok" fixed-width />
            <FontAwesomeIcon v-else-if="item.status" icon="fal fa-exclamation-triangle" class="text-red-500" v-tooltip="statusLabels[item.status]" fixed-width />
            <FontAwesomeIcon v-else icon="fal fa-circle" class="text-gray-300" v-tooltip="ctrans('Not read yet')" fixed-width />
        </template>
        <template #cell(name)="{ item }">
            <Link :href="route('grp.masters.competitors.show', { competitor: item.slug })" class="font-medium text-gray-700 hover:underline">
                {{ item.name }}
            </Link>
            <div>
                <a :href="item.website" target="_blank" rel="noopener noreferrer" class="text-xs text-gray-400 hover:underline">{{ item.website }}</a>
            </div>
            <div v-if="item.status && item.status !== 'ok'" class="text-xs text-red-600">
                {{ statusLabels[item.status] }}<span v-if="item.last_error">: {{ item.last_error }}</span>
            </div>
        </template>
        <template #cell(master_shop_name)="{ item }">
            <Link :href="route('grp.masters.master_shops.show', { masterShop: item.master_shop_slug })" class="font-medium text-gray-700 hover:underline whitespace-nowrap">
                {{ item.master_shop_name }}
            </Link>
        </template>
        <template #cell(read_method)="{ item }">
            <span class="whitespace-nowrap">{{ readMethodLabels[item.read_method] }}</span>
            <FontAwesomeIcon v-if="item.has_login" icon="fal fa-lock" class="ml-1 text-gray-500" v-tooltip="ctrans('Logs in with our trade account')" fixed-width />
            <span v-if="item.read_method === 'none'" class="ml-2 text-xs text-amber-600">{{ ctrans("Add a search link, or it is read by itself if it is a Shopify or WooCommerce shop") }}</span>
        </template>
        <template #cell(sells_to)="{ item }">
            <FontAwesomeIcon :icon="sellsToIcons[item.sells_to_key]" class="text-gray-500" v-tooltip="item.sells_to" fixed-width />
        </template>
        <template #cell(fetched_at)="{ item }">
            <span class="whitespace-nowrap">{{ item.fetched_at ? useFormatTime(item.fetched_at, { formatTime: "do MMM yy" }) : "" }}</span>
        </template>
        <template #cell(number_products)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.number_products) }}</span>
        </template>
        <template #cell(number_confirmed)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.number_confirmed) }}</span>
        </template>
        <template #cell(median_difference)="{ item }">
            <span v-if="item.median_difference !== null" class="tabular-nums" :class="item.median_difference < 0 ? 'text-red-600' : 'text-green-600'"
                v-tooltip="item.median_difference < 0 ? ctrans('They are cheaper') : ctrans('They are dearer')">
                {{ item.median_difference > 0 ? "+" : "" }}{{ item.median_difference }}%
            </span>
        </template>
    </Table>
</template>
