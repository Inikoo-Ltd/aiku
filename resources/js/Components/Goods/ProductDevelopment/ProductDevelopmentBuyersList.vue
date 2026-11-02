<!--
  - Author Louis Perez
  - Created on 26-10-2026-17h-40m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { nextTick, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Popover from "primevue/popover"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ProductDevelopmentRemoteSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRemoteSelect.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { notify } from "@kyvg/vue3-notification"
import { faShoppingCart, faCheckCircle, faClock, faCheck, faUndo, faTimes, faInfoCircle, faLink, faUnlink, faClipboardList } from "@fal"
library.add(faShoppingCart, faCheckCircle, faClock, faCheck, faUndo, faTimes, faInfoCircle, faLink, faUnlink, faClipboardList)

interface LinkedPurchaseOrder {
    id: number
    reference: string
    organisation: string
    state: string
    date: string | null
    ordered: number | null
    route: routeType
}

interface Row {
    trade_unit_id: number
    code: string
    name: string
    route: routeType
    skus: string[]
    required: number
    approved: boolean
    status: string
    purchase: { confirmed_at: string | null, confirmed_by: string | null, note: string | null } | null
    purchase_orders: LinkedPurchaseOrder[]
    can_confirm: boolean
    can_undo: boolean
}

const props = defineProps<{
    data: {
        rows: Row[]
        totals: { components: number, approved: number, confirmed: number }
        route: routeType
        link: { route: routeType, search_route: routeType } | null
    }
}>()

const formatQuantity = (quantity: number) => Number(quantity.toFixed(3)).toLocaleString()
const formatDate = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy" }) : ""

const confirmDialog = ref<{ row: Row, note: string } | null>(null)
const working = ref<number | null>(null)
const errors = ref<Record<string, string>>({})

/* Linking purchase orders: one per organisation the component is bought for */

const linkPopover = ref<InstanceType<typeof Popover> | null>(null)
const linkRow = ref<Row | null>(null)
const picked = ref<number | null>(null)
const linking = ref<string | null>(null)

function openLink(row: Row, event: Event) {
    const target = event.currentTarget as HTMLElement
    linkRow.value = row
    picked.value = null
    linkPopover.value?.hide()
    nextTick(() => linkPopover.value?.show(event, target))
}

function linkPurchaseOrder(row: Row, purchaseOrderId: number, operation: "link" | "unlink") {
    if (!props.data.link) {
        return
    }
    router.patch(route(props.data.link.route.name, props.data.link.route.parameters), { trade_unit_id: row.trade_unit_id, purchase_order_ids: [purchaseOrderId], operation }, {
        preserveScroll: true,
        only: ["buyers_list", "recentActivity"],
        onStart: () => linking.value = `${row.trade_unit_id}-${purchaseOrderId}`,
        onFinish: () => linking.value = null,
        onSuccess: () => {
            if (operation === "link") {
                linkPopover.value?.hide()
            }
        },
        onError: (validationErrors) => notify({ title: operation === "link" ? ctrans("Purchase order not linked") : ctrans("Purchase order not unlinked"), text: Object.values(validationErrors).join(" "), type: "error" }),
    })
}

function onPicked(option: { value: number, label: string }) {
    if (linkRow.value && !linkRow.value.purchase_orders.some((purchaseOrder) => purchaseOrder.id === option.value)) {
        linkPurchaseOrder(linkRow.value, option.value, "link")
    }
    setTimeout(() => picked.value = null)
}

function send(row: Row, confirmed: boolean, note: string | null) {
    router.patch(route(props.data.route.name, props.data.route.parameters), { trade_unit_id: row.trade_unit_id, confirmed, note }, {
        preserveScroll: true,
        only: ["buyers_list", "recentActivity"],
        onStart: () => working.value = row.trade_unit_id,
        onFinish: () => working.value = null,
        onSuccess: () => { confirmDialog.value = null; errors.value = {} },
        onError: (validationErrors) => errors.value = validationErrors,
    })
}
</script>

<template>
    <section v-if="data" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <header class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-gray-100 px-4 py-3">
            <FontAwesomeIcon icon="fal fa-shopping-cart" class="text-[--app-accent]" fixed-width aria-hidden="true" />
            <h3 class="text-sm font-semibold text-gray-900">{{ ctrans("Buyer's list") }}</h3>
            <span class="text-xs text-gray-500">{{ ctrans(":approved of :total approved for purchasing, :confirmed confirmed bought", { approved: String(data.totals.approved), total: String(data.totals.components), confirmed: String(data.totals.confirmed) }) }}</span>
        </header>
        <p class="flex items-start gap-2 border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs text-gray-600">
            <FontAwesomeIcon icon="fal fa-info-circle" class="mt-0.5 shrink-0 text-[--app-accent]" fixed-width aria-hidden="true" />
            {{ ctrans("A purchase order belongs to one organisation. If a component is bought for more than one organisation, link a purchase order from each; a component can have several.") }}
        </p>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[44rem] text-sm">
                <thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-medium text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ ctrans("Component") }}</th>
                        <th class="px-2 py-2 text-right">{{ ctrans("Needed") }}</th>
                        <th class="px-2 py-2">{{ ctrans("Status") }}</th>
                        <th class="px-2 py-2">{{ ctrans("Purchase orders") }}</th>
                        <th class="px-4 py-2" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in data.rows" :key="row.trade_unit_id" class="align-top">
                        <td class="px-4 py-2.5">
                            <a :href="route(row.route.name, row.route.parameters)" class="whitespace-nowrap font-medium text-gray-900 hover:text-[--app-accent]">{{ row.code }}</a>
                            <div class="text-xs text-gray-500">{{ row.name }}</div>
                            <div class="mt-0.5 text-xs text-gray-500">
                                {{ ctrans("For") }}:
                                <span v-for="(sku, index) in row.skus" :key="sku" class="whitespace-nowrap text-gray-700">{{ sku }}<template v-if="index < row.skus.length - 1">, </template></span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-2 py-2.5 text-right tabular-nums">{{ formatQuantity(row.required) }}</td>
                        <td class="px-2 py-2.5 text-xs">
                            <span class="inline-flex items-center gap-1 whitespace-nowrap" :class="row.approved ? 'text-green-700' : 'text-amber-700'">
                                <FontAwesomeIcon :icon="row.approved ? 'fal fa-check-circle' : 'fal fa-clock'" fixed-width aria-hidden="true" />
                                {{ row.status }}
                            </span>
                            <div class="mt-1">
                                <template v-if="row.purchase">
                                    <span class="font-medium text-green-700">{{ ctrans("Bought") }}</span>
                                    <span class="text-gray-500"> · {{ ctrans(":name, :date", { name: row.purchase.confirmed_by ?? "", date: formatDate(row.purchase.confirmed_at) }) }}</span>
                                    <div v-if="row.purchase.note" class="text-gray-700">{{ row.purchase.note }}</div>
                                </template>
                                <span v-else-if="row.approved" class="text-gray-400">{{ ctrans("Not confirmed bought yet") }}</span>
                            </div>
                        </td>
                        <td class="px-2 py-2.5 text-xs">
                            <ul v-if="row.purchase_orders.length" class="space-y-1.5">
                                <li v-for="purchaseOrder in row.purchase_orders" :key="purchaseOrder.id" class="group flex items-start gap-1.5">
                                    <FontAwesomeIcon icon="fal fa-clipboard-list" class="mt-0.5 shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                                    <span class="min-w-0 flex-1">
                                        <Link :href="route(purchaseOrder.route.name, purchaseOrder.route.parameters)" class="whitespace-nowrap font-medium text-gray-800 hover:text-[--app-accent]">{{ purchaseOrder.reference }}</Link>
                                        <span class="text-gray-500"> · {{ purchaseOrder.organisation }}</span>
                                        <span class="block text-gray-500">
                                            {{ purchaseOrder.state }} · {{ purchaseOrder.ordered !== null ? ctrans(":quantity ordered", { quantity: formatQuantity(purchaseOrder.ordered) }) : ctrans("not on this PO yet") }}
                                        </span>
                                    </span>
                                    <button v-if="data.link" type="button" class="shrink-0 rounded p-1 text-gray-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 focus:opacity-100 group-hover:opacity-100" v-tooltip="ctrans('Unlink')" :aria-label="ctrans('Unlink :reference', { reference: purchaseOrder.reference })" :disabled="linking === `${row.trade_unit_id}-${purchaseOrder.id}`" @click="linkPurchaseOrder(row, purchaseOrder.id, 'unlink')">
                                        <FontAwesomeIcon icon="fal fa-unlink" fixed-width aria-hidden="true" />
                                    </button>
                                </li>
                            </ul>
                            <span v-else class="text-gray-400">{{ ctrans("None linked") }}</span>
                            <button v-if="data.link" type="button" class="mt-1.5 flex items-center gap-1 whitespace-nowrap text-[--app-accent] hover:text-[--app-accent-strong]" @click="(event) => openLink(row, event)">
                                <FontAwesomeIcon icon="fal fa-link" fixed-width aria-hidden="true" />
                                {{ ctrans("Link purchase order") }}
                            </button>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right">
                            <Button v-if="row.can_undo" type="tertiary" size="xs" :label="ctrans('Undo')" :loading="working === row.trade_unit_id" @click="send(row, false, null)">
                                <template #icon><FontAwesomeIcon icon="fal fa-undo" class="text-orange-500" fixed-width aria-hidden="true" /></template>
                            </Button>
                            <Button v-else-if="row.can_confirm" type="tertiary" size="xs" :label="ctrans('Confirm bought')" @click="confirmDialog = { row, note: '' }">
                                <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!data.rows.length">
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No components yet. Add them per SKU in Formulation & trial runs.") }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Popover ref="linkPopover">
            <div v-if="linkRow && data.link" class="w-80 space-y-2 text-sm">
                <p class="text-xs text-gray-500">{{ ctrans("Link a purchase order to :code", { code: linkRow.code }) }}</p>
                <ProductDevelopmentRemoteSelect v-model="picked" :searchRoute="data.link.search_route" :placeholder="ctrans('Search by reference')" @picked="onPicked" />
                <p class="text-xs text-gray-500">{{ ctrans("Bought for several organisations? Link one purchase order from each.") }}</p>
            </div>
        </Popover>

        <Dialog :visible="!!confirmDialog" modal :header="confirmDialog ? ctrans('Confirm :code is bought', { code: confirmDialog.row.code }) : ''" :style="{ width: '28rem' }" :breakpoints="{ '640px': '94vw' }" @update:visible="(visible) => !visible && (confirmDialog = null)">
            <div v-if="confirmDialog" class="space-y-2 text-sm">
                <p class="text-gray-600">{{ ctrans(":quantity needed for :skus.", { quantity: formatQuantity(confirmDialog.row.required), skus: confirmDialog.row.skus.join(", ") }) }}</p>
                <label class="block text-xs text-gray-500">{{ ctrans("Note (optional)") }}</label>
                <InputText v-model="confirmDialog.note" maxlength="255" class="w-full" :placeholder="ctrans('e.g. PO 1234, 600 ordered from Glassworks')" />
                <p v-if="Object.keys(errors).length" class="text-xs text-red-600">{{ Object.values(errors)[0] }}</p>
            </div>
            <template #footer>
                <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="confirmDialog = null">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Confirm bought')" :loading="working === confirmDialog?.row.trade_unit_id" @click="confirmDialog && send(confirmDialog.row, true, confirmDialog.note || null)">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>
    </section>
</template>
