<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 27 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { reactive, ref, watch } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
    groupBy: string | null
    mixes: { artefact_id: number, code: string, name: string, unit: string, needed: number, on_hand: number, in_progress: number, shortfall: number, artisan: string | null, needed_for: string[] }[] | null
    artisanWorkload: { id: number, name: string, open_job_orders: number, hidden: boolean }[] | null
    groups: { label: string, items: { id: number, quantity: number, state: string, stock_code: string, stock_name: string, family: string | null, maker: string | null, buyer_code: string | null, customer_name: string | null, order_reference: string | null, job_order_reference: string | null, job_order_slug: string | null, priority: string, needed_by: string | null }[] }[] | null
    pickedOrders: {
        id: number
        reference: string
        net_amount: string
        currency_code: string
        buyer_name: string
        transactions_count: number
    }[]
}>()

const selected = reactive<Record<number, number>>({})

const hiddenGroupsKey = `to-produce-hidden-${props.groupBy}`
const hiddenGroups = ref<string[]>(JSON.parse(localStorage.getItem(hiddenGroupsKey) || "[]"))
watch(hiddenGroups, (value) => localStorage.setItem(hiddenGroupsKey, JSON.stringify(value)), { deep: true })

function toggleGroup(label: string) {
    const index = hiddenGroups.value.indexOf(label)
    index === -1 ? hiddenGroups.value.push(label) : hiddenGroups.value.splice(index, 1)
}

function toggle(item: { id: number, quantity: number }) {
    if (item.id in selected) {
        delete selected[item.id]
    } else {
        selected[item.id] = Number(item.quantity)
    }
}

function sendToWarehouse(orderId: number) {
    router.post(
        route("grp.org.productions.show.to_produce.send_to_warehouse", [route().params["organisation"], route().params["production"], orderId]),
        {},
        { preserveScroll: true }
    )
}

function createJobOrders() {
    router.post(
        route("grp.org.productions.show.to_produce.job_orders.store", [route().params["organisation"], route().params["production"]]),
        { ids: Object.keys(selected).map(Number) },
        { preserveScroll: true, onSuccess: () => { for (const k in selected) delete selected[k] } }
    )
}

const showHiddenArtisans = ref(false)

function toggleArtisan(artisan: { id: number, hidden: boolean }) {
    router.post(
        route(artisan.hidden ? "grp.org.productions.show.to_produce.artisans.show" : "grp.org.productions.show.to_produce.artisans.hide", [route().params["organisation"], route().params["production"], artisan.id]),
        {},
        { preserveScroll: true }
    )
}

const mixQuantities = reactive<Record<number, number>>({})

function toggleMix(mix: { artefact_id: number, shortfall: number }) {
    if (mix.artefact_id in mixQuantities) {
        delete mixQuantities[mix.artefact_id]
    } else {
        mixQuantities[mix.artefact_id] = Math.ceil(mix.shortfall) || 1
    }
}

function createMixJobOrders() {
    router.post(
        route("grp.org.productions.show.to_produce.mixes.job_orders.store", [route().params["organisation"], route().params["production"]]),
        { lines: Object.entries(mixQuantities).map(([artefact_id, quantity]) => ({ artefact_id: Number(artefact_id), quantity })) },
        { preserveScroll: true, onSuccess: () => { for (const k in mixQuantities) delete mixQuantities[k] } }
    )
}

function jobOrderHref(item: { job_order_slug: string }) {
    return route("grp.org.productions.show.operations.job-orders.show", [route().params["organisation"], route().params["production"], item.job_order_slug])
}

function submitCherryPick() {
    const lines = Object.entries(selected).map(([id, quantity]) => ({ id: Number(id), quantity }))
    router.post(
        route("grp.org.productions.show.to_produce.cherry_pick", [route().params["organisation"], route().params["production"]]),
        { lines },
        { preserveScroll: true, onSuccess: () => { for (const k in selected) delete selected[k] } }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div v-if="Object.keys(selected).length" class="sticky top-0 z-10 mx-4 mt-4 flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-2 text-white">
        <span>{{ Object.keys(selected).length }} {{ trans("lines selected") }}</span>
        <div class="flex gap-2">
            <button type="button" class="rounded bg-white px-3 py-1 text-indigo-600" @click="createJobOrders">
                {{ trans("Create job orders") }}
            </button>
            <button type="button" class="rounded bg-white px-3 py-1 text-indigo-600" @click="submitCherryPick">
                {{ trans("Pick into order") }}
            </button>
        </div>
    </div>

    <div v-if="pickedOrders?.length" class="mx-4 mt-4 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="border-b border-gray-200 px-4 py-2 font-medium dark:border-gray-700">
            {{ trans("Picked orders waiting to be sent to warehouse") }}
        </div>
        <div v-for="order in pickedOrders" :key="order.id" class="flex items-center justify-between px-4 py-2">
            <div class="flex items-center gap-4">
                <span class="font-medium">{{ order.buyer_name }}</span>
                <span class="text-gray-500">{{ order.reference }}</span>
                <span class="text-gray-500">{{ order.transactions_count }} {{ trans("lines") }}</span>
                <span class="tabular-nums">{{ useLocaleStore().currencyFormat(order.currency_code, Number(order.net_amount)) }}</span>
            </div>
            <button type="button" class="rounded bg-indigo-600 px-3 py-1 text-white" @click="sendToWarehouse(order.id)">
                {{ trans("Send to warehouse") }}
            </button>
        </div>
    </div>

    <div v-if="mixes" class="mx-4 mt-5">
        <div v-if="Object.keys(mixQuantities).length" class="sticky top-0 z-10 mb-4 flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-2 text-white">
            <span>{{ Object.keys(mixQuantities).length }} {{ trans("mixes selected") }}</span>
            <button type="button" class="rounded bg-white px-3 py-1 text-indigo-600" @click="createMixJobOrders">{{ trans("Create job orders") }}</button>
        </div>
        <div v-if="!mixes.length" class="rounded-lg border border-dashed border-gray-300 px-4 py-10 text-center text-gray-400">
            {{ trans("No mixes needed. Mixes appear here when an open job order uses a raw material that is made in-house.") }}
        </div>
        <table v-else class="w-full text-sm">
            <thead class="text-left text-gray-500">
                <tr>
                    <th class="w-36 px-4 py-2"></th>
                    <th class="px-2 py-2">{{ trans("Mix") }}</th>
                    <th class="px-2 py-2">{{ trans("Prepared by") }}</th>
                    <th class="px-2 py-2">{{ trans("Needed for") }}</th>
                    <th class="px-2 py-2 text-right">{{ trans("Needed") }}</th>
                    <th class="px-2 py-2 text-right">{{ trans("On hand") }}</th>
                    <th class="px-2 py-2 text-right">{{ trans("Being made") }}</th>
                    <th class="px-4 py-2 text-right">{{ trans("Short") }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="mix in mixes" :key="mix.artefact_id" class="border-t border-gray-100 dark:border-gray-800">
                    <td class="px-4 py-1.5">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" :checked="mix.artefact_id in mixQuantities" @change="toggleMix(mix)" />
                            <input v-if="mix.artefact_id in mixQuantities" v-model.number="mixQuantities[mix.artefact_id]" type="number" min="1" class="w-24 rounded border-gray-300" />
                        </div>
                    </td>
                    <td class="px-2 py-1.5"><span class="font-medium">{{ mix.code }}</span> <span class="text-gray-500">{{ mix.name }}</span></td>
                    <td class="px-2 py-1.5 text-gray-500">{{ mix.artisan ?? '-' }}</td>
                    <td class="px-2 py-1.5 text-gray-500">{{ mix.needed_for.join(', ') }}</td>
                    <td class="px-2 py-1.5 text-right tabular-nums">{{ useLocaleStore().number(mix.needed) }} {{ mix.unit }}</td>
                    <td class="px-2 py-1.5 text-right tabular-nums">{{ useLocaleStore().number(mix.on_hand) }}</td>
                    <td class="px-2 py-1.5 text-right tabular-nums">{{ useLocaleStore().number(mix.in_progress) }}</td>
                    <td class="px-4 py-1.5 text-right tabular-nums" :class="mix.shortfall > 0 ? 'font-semibold text-red-600' : 'text-gray-400'">{{ useLocaleStore().number(mix.shortfall) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div v-if="artisanWorkload" class="mx-4 mt-5 rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
        <div class="mb-2 flex items-center gap-2 text-sm text-gray-500">
            <span>{{ trans("Open job orders per artisan") }} <span class="text-gray-400">· {{ trans("everyone should have at least two") }}</span></span>
            <button v-if="artisanWorkload.some(artisan => artisan.hidden)" type="button" class="ml-auto text-xs text-gray-400 hover:text-indigo-600" @click="showHiddenArtisans = !showHiddenArtisans">
                {{ artisanWorkload.filter(artisan => artisan.hidden).length }} {{ trans("not artisans") }} · {{ showHiddenArtisans ? trans("hide") : trans("show") }}
            </button>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <span
                v-for="artisan in artisanWorkload.filter(artisan => !artisan.hidden)"
                :key="artisan.id"
                class="flex items-center gap-1 rounded-full border px-2.5 py-px text-xs"
                :class="artisan.open_job_orders === 0 ? 'border-red-300 bg-red-50 text-red-700' : artisan.open_job_orders === 1 ? 'border-amber-300 bg-amber-50 text-amber-700' : 'border-gray-200 text-gray-600'">
                {{ artisan.name }} · {{ artisan.open_job_orders }}
                <button type="button" class="opacity-40 hover:opacity-100" :title="trans('Not an artisan')" @click="toggleArtisan(artisan)">×</button>
            </span>
        </div>
        <div v-if="showHiddenArtisans" class="mt-2 flex flex-wrap gap-1.5">
            <span v-for="artisan in artisanWorkload.filter(artisan => artisan.hidden)" :key="artisan.id" class="flex items-center gap-1 rounded-full border border-dashed border-gray-300 px-2.5 py-px text-xs text-gray-400">
                {{ artisan.name }}
                <button type="button" class="hover:text-indigo-600" :title="trans('Is an artisan')" @click="toggleArtisan(artisan)">+</button>
            </span>
        </div>
    </div>

    <div v-if="groups" class="mx-4 mt-5 space-y-6">
        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="group in groups"
                :key="group.label"
                type="button"
                class="rounded-full border px-2.5 py-px text-xs"
                :class="hiddenGroups.includes(group.label) ? 'border-gray-200 text-gray-400 hover:bg-gray-50' : 'border-indigo-500 bg-indigo-50 text-indigo-700'"
                @click="toggleGroup(group.label)">
                {{ group.items.length }} {{ group.label }}
            </button>
        </div>
        <div v-for="group in groups.filter(group => !hiddenGroups.includes(group.label))" :key="group.label" class="rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-2 font-medium dark:border-gray-700 dark:bg-gray-800">
                <span>{{ group.label }}</span>
                <span class="text-gray-500">{{ group.items.length }} {{ trans("lines") }}</span>
            </div>
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="item in group.items" :key="item.id" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <td class="w-36 px-4 py-1.5">
                            <div class="flex items-center gap-2">
                                <input v-if="item.state === 'open'" type="checkbox" :checked="item.id in selected" @change="toggle(item)" />
                                <input v-if="item.id in selected" v-model.number="selected[item.id]" type="number" step="0.001" :max="item.quantity" min="0.001" class="w-24 rounded border-gray-300" />
                            </div>
                        </td>
                        <td v-if="groupBy !== 'buyer_code'" class="w-40 px-2 py-1.5">{{ item.buyer_code ?? item.customer_name }}</td>
                        <td class="w-32 px-2 py-1.5">{{ item.stock_code }}</td>
                        <td class="px-2 py-1.5">{{ item.stock_name }}</td>
                        <td v-if="groupBy !== 'family'" class="w-40 px-2 py-1.5 text-gray-500">{{ item.family }}</td>
                        <td v-if="groupBy !== 'maker'" class="w-32 px-2 py-1.5 text-gray-500">{{ item.maker }}</td>
                        <td class="w-24 px-2 py-1.5 text-right tabular-nums">{{ useLocaleStore().number(Number(item.quantity)) }}</td>
                        <td class="w-24 px-2 py-1.5 text-gray-500">{{ item.priority }}</td>
                        <td class="w-28 px-2 py-1.5 text-gray-500 whitespace-nowrap">{{ item.needed_by ? useFormatTime(item.needed_by, { formatTime: "mdy" }) : "-" }}</td>
                        <td class="w-20 px-4 py-1.5 text-gray-500">{{ item.state }}</td>
                        <td class="w-32 px-2 py-1.5"><Link v-if="item.job_order_slug" :href="jobOrderHref(item)" class="primaryLink">{{ item.job_order_reference }}</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Table v-else-if="!mixes" :resource="data" class="mt-5">
        <template #cell(buyer_code)="{ item }">
            <span v-if="item.buyer_code">{{ item.buyer_code }}</span>
            <span v-else>{{ item.customer_name }} <span class="text-gray-500">{{ item.order_reference }}</span></span>
        </template>
        <template #cell(stock_code)="{ item }">
            <div class="whitespace-nowrap">{{ item.stock_code }} <span v-if="item.family" class="ml-1 rounded-full bg-gray-100 border border-gray-200 px-2 py-0.5 text-xs text-gray-600">{{ item.family }}</span></div>
            <div class="text-gray-500">{{ item.stock_name }}</div>
        </template>
        <template #cell(job_order_reference)="{ item }">
            <Link v-if="item.job_order_slug" :href="jobOrderHref(item)" class="primaryLink">{{ item.job_order_reference }}</Link>
        </template>
        <template #cell(pick)="{ item }">
            <div class="flex items-center gap-2">
                <input v-if="item.state === 'open'" type="checkbox" :checked="item.id in selected" @change="toggle(item)" />
                <input
                    v-if="item.id in selected"
                    v-model.number="selected[item.id]"
                    type="number"
                    step="0.001"
                    :max="item.quantity"
                    min="0.001"
                    class="w-24 rounded border-gray-300"
                />
            </div>
        </template>
        <template #cell(quantity)="{ item }">
            <span class="tabular-nums">{{ useLocaleStore().number(Number(item.quantity)) }}</span>
        </template>
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ useFormatTime(item.created_at, { formatTime: "mdy" }) }}</span>
        </template>
    </Table>
</template>
