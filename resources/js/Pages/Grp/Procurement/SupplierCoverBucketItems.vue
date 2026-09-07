<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 31 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"

type BucketItem = {
    id: number
    slug: string
    code: string
    name: string
    cost: number | null
    units_per_carton: number | null
    lead_time_days: number | null
    lead_time_measured: boolean
    our_stock: number | null
    our_days_of_cover: number | null
    recommended_quantity: number | null
    shopping_list_item_id: number | null
    ordered_quantity: number
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    orgSupplier: { id: number, slug: string, currency: string }
    bucket: string
    bucketLabel: string
    rank: string | null
    items: { data: BucketItem[], total: number, prev_page_url: string | null, next_page_url: string | null, from: number | null, to: number | null }
}>()

const quantities = ref<Record<number, number>>({})
const commitTimers: Record<number, ReturnType<typeof setTimeout>> = {}

function quantityFor(item: BucketItem): number {
    return quantities.value[item.id] ?? item.ordered_quantity ?? 0
}

function setQuantity(item: BucketItem, quantity: number) {
    quantities.value[item.id] = Math.max(0, quantity)
    if (commitTimers[item.id]) {
        clearTimeout(commitTimers[item.id])
    }
    commitTimers[item.id] = setTimeout(() => commitQuantity(item), 600)
}

function commitQuantity(item: BucketItem) {
    const quantity = quantityFor(item)
    if (quantity === (item.ordered_quantity ?? 0)) {
        return
    }

    const reloadOptions = {
        preserveScroll: true,
        preserveState: true,
        only: ["items"],
        onSuccess: () => { delete quantities.value[item.id] },
    }

    const organisation = route().params["organisation"]

    if (item.shopping_list_item_id) {
        if (quantity <= 0) {
            router.delete(route("grp.org.procurement.shopping_list.destroy", [organisation, item.shopping_list_item_id]), reloadOptions)
        } else {
            router.patch(
                route("grp.org.procurement.shopping_list.update", [organisation, item.shopping_list_item_id]),
                { quantity_units: quantity },
                reloadOptions
            )
        }
    } else if (quantity > 0) {
        router.post(
            route("grp.org.procurement.shopping_list.store", [organisation, item.slug]),
            { quantity_units: quantity },
            reloadOptions
        )
    }
}

const cartonsOf = (item: BucketItem) =>
    item.units_per_carton && item.units_per_carton > 0 ? Math.round((quantityFor(item) / item.units_per_carton) * 100) / 100 : null

const amountOf = (item: BucketItem) => quantityFor(item) * Number(item.cost ?? 0)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-5">
        <div class="mb-2 flex items-baseline justify-between text-sm text-gray-500">
            <span>
                <b class="font-semibold text-gray-800">{{ bucketLabel }}</b>
                <span v-if="rank"> · {{ rank }}</span>
                — {{ useLocaleStore().number(items.total) }} {{ trans("products") }}
            </span>
            <span v-if="items.from">{{ items.from }}–{{ items.to }} / {{ useLocaleStore().number(items.total) }}</span>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ trans("Code") }}</th>
                        <th class="px-4 py-2">{{ trans("Info") }}</th>
                        <th class="px-4 py-2 text-right">{{ trans("Quantity (units)") }}</th>
                        <th class="px-4 py-2 text-right">{{ trans("Cartons") }}</th>
                        <th class="px-4 py-2 text-right">{{ trans("Amount") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in items.data" :key="item.id">
                        <td class="whitespace-nowrap px-4 py-2 align-top font-medium text-gray-700">{{ item.code }}</td>
                        <td class="max-w-md px-4 py-2">
                            <div class="min-w-0 overflow-hidden text-xs leading-5">
                                <div class="truncate text-sm font-medium text-gray-800">{{ item.name }}</div>
                                <div class="text-gray-500">
                                    {{ trans("our stock") }}
                                    <b v-if="item.our_stock !== null" class="font-medium tabular-nums text-gray-700">{{ useLocaleStore().number(Math.floor(item.our_stock)) }}</b>
                                    <b v-else class="font-medium text-violet-600">{{ trans("never stocked") }}</b>
                                    <template v-if="item.our_days_of_cover !== null">
                                        ·
                                        <span :class="{ 'font-medium text-red-600': item.our_days_of_cover <= 14, 'text-amber-600': item.our_days_of_cover > 14 && item.our_days_of_cover <= 30 }">
                                            {{ item.our_days_of_cover === 0 ? trans("we run out now") : `${trans("we run out in")} ~${item.our_days_of_cover} ${trans("days")}` }}
                                        </span>
                                    </template>
                                    <template v-if="item.lead_time_days !== null">
                                        · <span :class="item.lead_time_measured ? 'text-gray-700' : 'text-gray-400'">
                                            {{ item.lead_time_days }} {{ trans("days lead") }}
                                            <span v-if="!item.lead_time_measured">({{ trans("estimate") }})</span>
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-2">
                                <NumberWithButtonSave
                                    :modelValue="quantityFor(item)"
                                    :bindToTarget="{ min: 0 }"
                                    allowZero
                                    isWithRefreshModel
                                    noUndoButton
                                    noSaveButton
                                    @update:modelValue="(value: number) => setQuantity(item, value)"
                                />
                                <button
                                    type="button"
                                    class="cursor-pointer whitespace-nowrap rounded-md border border-dashed px-2 py-1 text-xs font-medium tabular-nums"
                                    :class="item.recommended_quantity ? 'border-indigo-300 text-indigo-600 hover:bg-indigo-50' : 'border-gray-200 text-gray-400 hover:bg-gray-50'"
                                    :title="trans('Suggested order, click to fill')"
                                    @click="setQuantity(item, item.recommended_quantity ?? 0)"
                                >
                                    {{ useLocaleStore().number(item.recommended_quantity ?? 0) }}
                                    <span class="ml-0.5 font-normal text-gray-400">{{ trans("suggested") }}</span>
                                </button>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2 text-right align-top tabular-nums text-gray-500">
                            {{ cartonsOf(item) ?? "—" }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-2 text-right align-top tabular-nums">
                            {{ item.cost !== null && quantityFor(item) ? useLocaleStore().currencyFormat(orgSupplier.currency, amountOf(item)) : "-" }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!items.data.length" class="py-10 text-center text-sm text-gray-400">
                {{ trans("No products in this bucket") }}
            </p>
        </div>

        <div v-if="items.prev_page_url || items.next_page_url" class="mt-3 flex justify-between text-sm">
            <Link v-if="items.prev_page_url" :href="items.prev_page_url" preserve-scroll class="secondaryLink">{{ trans("Previous") }}</Link>
            <span v-else />
            <Link v-if="items.next_page_url" :href="items.next_page_url" preserve-scroll class="secondaryLink">{{ trans("Next") }}</Link>
        </div>
    </div>
</template>
