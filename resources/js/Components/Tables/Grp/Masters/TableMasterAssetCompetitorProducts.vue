<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faExternalLink } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(faCheck, faTimes, faExternalLink)

const props = defineProps<{
    data: object
    tab?: string
    canEdit?: boolean
}>()

const locale = inject("locale", aikuLocaleStructure)
const reviewing = ref<number | null>(null)

const review = (id: number, status: string) => {
    reviewing.value = id
    router.patch(route("grp.models.master_asset_competitor_product.review", { masterAssetCompetitorProduct: id }), { status }, {
        preserveScroll: true,
        onFinish: () => {
            reviewing.value = null
            router.reload({ only: [props.tab as string] })
        },
    })
}

const money = (value: number | null, currencyCode: string) => value === null ? "" : locale.currencyFormat(currencyCode, Number(value))
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item }">
            <div class="font-medium">{{ item.code }}</div>
            <div class="text-xs text-gray-500">{{ item.name }}</div>
        </template>
        <template #cell(our_unit_price)="{ item }">
            {{ money(item.our_unit_price, item.currency_code) }}
        </template>
        <template #cell(competitor_product_name)="{ item }">
            <img v-if="item.competitor_image_url" :src="item.competitor_image_url" alt="" loading="lazy" class="float-left mr-2 h-10 w-10 rounded object-cover" />
            <a :href="item.competitor_product_url" target="_blank" rel="noopener noreferrer" class="primaryLink">
                {{ item.competitor_product_name }}
                <FontAwesomeIcon icon="fal fa-external-link" class="text-xs" fixed-width />
            </a>
            <div class="text-xs text-gray-500">
                <span v-if="item.competitor_units > 1">{{ ctrans("Pack of :units", { units: item.competitor_units }) }}</span>
                <span v-if="item.minimum_order" class="ml-2">{{ ctrans("Minimum :n", { n: item.minimum_order }) }}</span>
                <span v-if="!item.is_same_item" class="ml-2 text-amber-600">{{ ctrans("Similar, not the same item") }}</span>
                <span v-if="item.confidence !== null" class="ml-2" v-tooltip="ctrans('How sure the AI is of this match')">{{ item.confidence }}%</span>
            </div>
        </template>
        <template #cell(competitor_unit_price)="{ item }">
            <span v-if="item.competitor_unit_price !== null">{{ money(item.competitor_unit_price, item.currency_code) }}</span>
            <span v-else class="text-xs text-gray-400">{{ ctrans("Price hidden") }}</span>
        </template>
        <template #cell(difference)="{ item }">
            <span v-if="item.difference !== null" :class="item.difference < 0 ? 'text-red-600' : 'text-green-600'"
                v-tooltip="item.difference < 0 ? ctrans('They are cheaper') : ctrans('They are dearer')">
                {{ item.difference > 0 ? "+" : "" }}{{ item.difference }}%
            </span>
        </template>
        <template #cell(status)="{ item }">
            <div class="flex items-center gap-x-1">
                <span v-if="item.is_auto_confirmed" class="text-xs text-gray-400" v-tooltip="ctrans('Confirmed by the AI, it was sure')">{{ ctrans("auto") }}</span>
                <button type="button" :disabled="!canEdit || reviewing === item.id" @click="review(item.id, 'confirmed')"
                    class="rounded px-2 py-0.5 text-xs border"
                    :class="item.status === 'confirmed' ? 'bg-green-500 text-white border-green-500' : 'border-gray-300 text-gray-600 hover:bg-green-50'"
                    v-tooltip="ctrans('Right match')">
                    <FontAwesomeIcon icon="fal fa-check" fixed-width />
                </button>
                <button type="button" :disabled="!canEdit || reviewing === item.id" @click="review(item.id, 'rejected')"
                    class="rounded px-2 py-0.5 text-xs border"
                    :class="item.status === 'rejected' ? 'bg-red-500 text-white border-red-500' : 'border-gray-300 text-gray-600 hover:bg-red-50'"
                    v-tooltip="ctrans('Wrong match')">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width />
                </button>
            </div>
        </template>
    </Table>
</template>
