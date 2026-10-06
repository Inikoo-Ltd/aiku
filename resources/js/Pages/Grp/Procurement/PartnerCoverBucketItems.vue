<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 31 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Image from "@common/Components/Image.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { snapToBatch } from "@/Composables/snapToBatch"
import { PageHeadingTypes } from "@/types/PageHeading"

type BucketItem = {
    id: number
    slug: string
    code: string
    name: string
    image: object | null
    their_stock: number
    our_stock: number | null
    our_days_of_cover: number | null
    recommended_quantity: number | null
    price: number | null
    shopping_list_item_id: number | null
    ordered_quantity: number
    order_quantum: number
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    orgPartner: { id: number, slug: string, currency: string }
    addRoute: { name: string, parameters: (string | number)[] }
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
    const quantity = snapToBatch(item.ordered_quantity ?? 0, quantityFor(item), item.order_quantum)
    quantities.value[item.id] = quantity
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
            router.delete(
                route("grp.org.procurement.org_partners.show.shopping_list.destroy", [organisation, props.orgPartner.id, item.shopping_list_item_id]),
                reloadOptions
            )
        } else {
            router.patch(
                route("grp.org.procurement.org_partners.show.shopping_list.update", [organisation, props.orgPartner.id, item.shopping_list_item_id]),
                { quantity },
                reloadOptions
            )
        }
    } else if (quantity > 0) {
        router.post(
            route(props.addRoute.name, [...props.addRoute.parameters, item.id]),
            { quantity },
            reloadOptions
        )
    }
}

const amountOf = (item: BucketItem) => quantityFor(item) * Number(item.price ?? 0)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-5">
        <div class="mb-2 flex items-baseline justify-between text-sm text-gray-500">
            <span>
                <b class="font-semibold text-gray-800">{{ bucketLabel }}</b>
                <span v-if="rank"> · {{ rank }}</span>
                — {{ useLocaleStore().number(items.total) }} {{ ctrans("items") }}
            </span>
            <span v-if="items.from">{{ items.from }}–{{ items.to }} / {{ useLocaleStore().number(items.total) }}</span>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ ctrans("Code") }}</th>
                        <th class="px-4 py-2">{{ ctrans("Info") }}</th>
                        <th class="px-4 py-2 text-right">{{ ctrans("Quantity (SKO)") }}</th>
                        <th class="px-4 py-2 text-right">{{ ctrans("Amount") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in items.data" :key="item.id">
                        <td class="whitespace-nowrap px-4 py-2 align-top font-medium text-gray-700">{{ item.code }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-start gap-3">
                                <div class="h-12 w-12 shrink-0 rounded bg-gray-50">
                                    <Image :src="item.image" imageCover />
                                </div>
                                <div class="min-w-0 text-xs leading-5">
                                    <div class="truncate text-sm font-medium text-gray-800">{{ item.name }}</div>
                                    <div class="text-gray-500">
                                        {{ ctrans("Their stock") }} <b class="font-medium text-gray-700 tabular-nums">{{ useLocaleStore().number(Math.floor(item.their_stock)) }}</b>
                                        · {{ ctrans("our stock") }}
                                        <b v-if="item.our_stock !== null" class="font-medium text-gray-700 tabular-nums">{{ useLocaleStore().number(Math.floor(item.our_stock)) }}</b>
                                        <b v-else class="font-medium text-violet-600">{{ ctrans("never stocked") }}</b>
                                        <template v-if="item.our_days_of_cover !== null">
                                            ·
                                            <span :class="{ 'font-medium text-red-600': item.our_days_of_cover <= 14, 'text-amber-600': item.our_days_of_cover > 14 && item.our_days_of_cover <= 30 }">
                                                {{ item.our_days_of_cover === 0 ? ctrans("we run out now") : `${ctrans("Estimated: Would run out in")} ~${item.our_days_of_cover} ${ctrans("days")}` }}
                                            </span>
                                        </template>
                                    </div>
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
                                <span
                                    v-if="item.order_quantum > 1"
                                    v-tooltip="ctrans('Made in batches: ordered in multiples of :quantum SKOs', { quantum: item.order_quantum })"
                                    class="cursor-help text-xs tabular-nums text-gray-400"
                                >×{{ item.order_quantum }}</span>
                                <button
                                    type="button"
                                    class="cursor-pointer whitespace-nowrap rounded-md border border-dashed px-2 py-1 text-xs font-medium tabular-nums"
                                    :class="item.recommended_quantity ? 'border-indigo-300 text-indigo-600 hover:bg-indigo-50' : 'border-gray-200 text-gray-400 hover:bg-gray-50'"
                                    :title="ctrans('Suggested order, click to fill')"
                                    @click="setQuantity(item, item.recommended_quantity ?? 0)"
                                >
                                    {{ useLocaleStore().number(item.recommended_quantity ?? 0) }}
                                    <span class="ml-0.5 font-normal text-gray-400">{{ ctrans("suggested") }}</span>
                                </button>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2 text-right align-top tabular-nums">
                            {{ item.price !== null && quantityFor(item) ? useLocaleStore().currencyFormat(orgPartner.currency, amountOf(item)) : "-" }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!items.data.length" class="py-10 text-center text-sm text-gray-400">
                {{ ctrans("No items in this bucket") }}
            </p>
        </div>

        <div v-if="items.prev_page_url || items.next_page_url" class="mt-3 flex justify-between text-sm">
            <Link v-if="items.prev_page_url" :href="items.prev_page_url" preserve-scroll class="secondaryLink">{{ ctrans("Previous") }}</Link>
            <span v-else />
            <Link v-if="items.next_page_url" :href="items.next_page_url" preserve-scroll class="secondaryLink">{{ ctrans("Next") }}</Link>
        </div>
    </div>
</template>
