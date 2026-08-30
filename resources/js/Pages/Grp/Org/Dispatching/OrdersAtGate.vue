<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 30 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { reactive, ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
    shortfall: object
    currency_code: string
}>()

const currentTab = ref<"queue" | "shortfall">(
    new URLSearchParams(window.location.search).get("gateTab") === "shortfall" ? "shortfall" : "queue"
)

function switchTab(tab: "queue" | "shortfall") {
    currentTab.value = tab
    const url = new URL(window.location.href)
    url.searchParams.set("gateTab", tab)
    window.history.replaceState(window.history.state, "", url)
}

const releasing = ref<number | null>(null)
const selectedShortfall = reactive<Record<number, number>>({})

function toggleShortfall(item: { org_stock_id: number, quantity_short: number }) {
    if (item.org_stock_id in selectedShortfall) {
        delete selectedShortfall[item.org_stock_id]
    } else {
        selectedShortfall[item.org_stock_id] = Math.ceil(Number(item.quantity_short))
    }
}

const creatingJobOrder = ref(false)

function createJobOrder() {
    creatingJobOrder.value = true
    const lines = Object.entries(selectedShortfall).map(([org_stock_id, quantity]) => ({
        org_stock_id: Number(org_stock_id),
        quantity,
    }))
    router.post(
        route("grp.org.warehouses.show.dispatching.gate.job_order", [route().params["organisation"], route().params["warehouse"]]),
        { lines },
        {
            preserveScroll: true,
            onSuccess: () => { for (const k in selectedShortfall) delete selectedShortfall[k] },
            onFinish: () => (creatingJobOrder.value = false),
        }
    )
}

const channelIcon = (type?: string) => {
    switch (type) {
        case "website": return ["fal", "globe"]
        case "phone": return ["fal", "phone"]
        case "email": return ["fal", "paper-plane"]
        case "showroom": return ["fal", "store"]
        case "marketplace":
        case "platform": return ["fal", "parachute-box"]
        case "api": return ["fal", "robot"]
        default: return ["fal", "shopping-basket"]
    }
}

function release(item: { id: number }) {
    releasing.value = item.id
    router.patch(
        route("grp.models.order.state.release_from_gate", [item.id]),
        {},
        { preserveScroll: true, onFinish: () => (releasing.value = null) }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-4 flex gap-1 border-b border-gray-200">
        <button
            type="button"
            class="px-4 py-2 text-sm font-medium"
            :class="currentTab === 'queue' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-gray-500 hover:text-gray-700'"
            @click="switchTab('queue')"
        >
            {{ trans("Queue") }}
        </button>
        <button
            type="button"
            class="px-4 py-2 text-sm font-medium"
            :class="currentTab === 'shortfall' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-gray-500 hover:text-gray-700'"
            @click="switchTab('shortfall')"
        >
            {{ trans("Shortfall") }}
        </button>
    </div>

    <div
        v-if="currentTab === 'shortfall' && Object.keys(selectedShortfall).length"
        class="sticky top-0 z-10 mx-4 mt-2 flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-2 text-white"
    >
        <span>{{ Object.keys(selectedShortfall).length }} {{ trans("stocks selected") }}</span>
        <Button
            type="tertiary"
            icon="fal fa-hammer"
            :label="trans('Create job order')"
            size="xs"
            :loading="creatingJobOrder"
            @click="createJobOrder"
        />
    </div>

    <Table v-show="currentTab === 'shortfall'" :resource="shortfall" name="shortfall" class="mt-2">
        <template #cell(make)="{ item }">
            <div class="flex items-center gap-2">
                <input
                    type="checkbox"
                    :checked="item.org_stock_id in selectedShortfall"
                    @change="toggleShortfall(item)"
                />
                <input
                    v-if="item.org_stock_id in selectedShortfall"
                    v-model.number="selectedShortfall[item.org_stock_id]"
                    type="number"
                    step="1"
                    min="1"
                    class="w-20 rounded border-gray-300"
                />
            </div>
        </template>
        <template #cell(quantity_required)="{ item }">
            <span class="tabular-nums">{{ useLocaleStore().number(Number(item.quantity_required)) }}</span>
        </template>
        <template #cell(quantity_available)="{ item }">
            <span class="tabular-nums text-gray-500">{{ useLocaleStore().number(Number(item.quantity_available)) }}</span>
        </template>
        <template #cell(quantity_short)="{ item }">
            <span class="tabular-nums font-semibold text-red-600">{{ useLocaleStore().number(Number(item.quantity_short)) }}</span>
        </template>
        <template #cell(blocked_amount)="{ item }">
            <span class="tabular-nums">{{ useLocaleStore().currencyFormat(currency_code, Number(item.blocked_amount)) }}</span>
        </template>
    </Table>

    <Table v-show="currentTab === 'queue'" :resource="data" class="mt-2">
        <template #cell(reference)="{ item }">
            <span class="inline-flex items-center gap-1.5">
                <FontAwesomeIcon
                    :icon="channelIcon(item.sales_channel_type)"
                    class="text-gray-400"
                    :title="item.sales_channel_code"
                    fixed-width
                />
                <Link
                    :href="route('grp.org.shops.show.ordering.orders.show', [route().params['organisation'], item.shop_slug, item.slug])"
                    class="font-mono text-indigo-600 hover:underline"
                >
                    {{ item.reference }}
                </Link>
            </span>
        </template>
        <template #cell(coverage)="{ item }">
            <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="item.ready_lines >= item.total_lines && item.total_lines > 0
                    ? 'bg-green-100 text-green-800'
                    : 'bg-amber-100 text-amber-800'"
            >
                {{ item.ready_lines }}/{{ item.total_lines }} {{ trans("ready") }}
            </span>
        </template>
        <template #cell(net_amount)="{ item }">
            <span class="inline-flex items-center gap-1.5">
                <FontAwesomeIcon
                    v-if="item.pay_status === 'paid'"
                    :icon="['fas', 'check-circle']"
                    class="text-green-600"
                    :title="trans('Paid')"
                />
                <FontAwesomeIcon
                    v-else
                    :icon="['fal', 'circle']"
                    class="text-amber-500"
                    :title="trans('Unpaid')"
                />
                <span class="tabular-nums">{{ useLocaleStore().currencyFormat(item.currency_code, Number(item.net_amount)) }}</span>
            </span>
        </template>
        <template #cell(at_gate_at)="{ item }">
            {{ useFormatTime(item.at_gate_at, { formatTime: "mdy" }) }}
        </template>
        <template #cell(release)="{ item }">
            <Button
                type="secondary"
                :icon="['fal', 'truck-loading']"
                :label="trans('Release DN')"
                size="xs"
                :loading="releasing === item.id"
                @click="release(item)"
            />
        </template>
    </Table>
</template>
