<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 17 Jul 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import Table from '@/Components/Table/Table.vue'
import { useLocaleStore } from '@/Stores/locale'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBox, faSpellCheck, faBoxCheck } from '@fal'
import { faExclamationCircle, faSpinner } from '@fas'
import ConfirmPopup from 'primevue/confirmpopup'
import { useConfirm } from 'primevue/useconfirm'

library.add(faBox, faSpellCheck, faBoxCheck, faExclamationCircle, faSpinner)

const props = defineProps<{
    data: object,
    tab?: string
}>()

const locale = useLocaleStore()
const confirm = useConfirm()

const changingId = ref<number | null>(null)

function confirmChangeState(event: MouseEvent, item: any, stateRoute: any, message: string, acceptLabel: string) {
    if (!stateRoute) {
        return
    }

    confirm.require({
        target: event.currentTarget as HTMLElement,
        message,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel,
        rejectLabel: trans('Cancel'),
        acceptClass: 'p-button-success',
        rejectClass: 'p-button-text',
        accept: () => changeState(item, stateRoute),
    })
}

async function changeState(item: any, stateRoute: any) {
    if (!stateRoute) {
        return
    }

    changingId.value = item.id
    try {
        const method = String(stateRoute.method ?? 'patch').toLowerCase()
        await axios[method](route(stateRoute.name, stateRoute.parameters))
        notify({ title: trans('Success'), text: trans('Item state updated'), type: 'success' })
        router.reload({ only: [props.tab ?? 'items', 'timelines', 'stock_delivery'] })
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error?.response?.data?.message || trans('Failed to update item state'),
            type: 'error',
        })
    } finally {
        changingId.value = null
    }
}

function formatQuantity(value: number) {
    return locale.number(Math.round(value * 1000) / 1000)
}

function skosPerCarton(item: any) {
    const pack = Number(item.units_per_pack) || 1
    const carton = Number(item.units_per_carton) || 1

    return carton / pack
}

function quantityBreakdown(item: any) {
    const units = Number(item.unit_quantity)
    const pack = Number(item.units_per_pack) || 1
    const carton = Number(item.units_per_carton) || 1

    return `${formatQuantity(units)}u. | ${formatQuantity(units / pack)}sko. | ${formatQuantity(units / carton)}C.`
}

function amount(item: any) {
    const net = locale.currencyFormat(item.net_currency ?? 'EUR', item.net_amount ?? 0)

    if (item.org_net_amount === null || item.org_currency === item.net_currency) {
        return `${net}`
    }

    return `${net} (${locale.currencyFormat(item.org_currency ?? 'EUR', item.org_net_amount)})`
}

function supplierProductRoute(item: { slug?: string }) {
    if (!item.slug) {
        return ''
    }

    return route('grp.supply-chain.supplier_products.show', [item.slug])
}

function orgStockRoute(item: { org_stock_id?: number }) {
    if (!item.org_stock_id) {
        return ''
    }

    return route('grp.majordomo.redirect_org_stock', [item.org_stock_id])
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item }">
            <div class="flex items-center gap-1.5">
                <Link
                    v-if="supplierProductRoute(item)"
                    v-tooltip="trans('Supplier product code')"
                    :href="supplierProductRoute(item)"
                    class="primaryLink"
                >
                    {{ item.code }}
                </Link>
                <span v-else>{{ item.code }}</span>

                <Link
                    v-if="orgStockRoute(item)"
                    v-tooltip="trans('Part reference is same as supplier product code')"
                    :href="orgStockRoute(item)"
                    class="text-gray-400 hover:text-gray-600"
                >
                    <FontAwesomeIcon icon="fal fa-box" aria-hidden="true" fixed-width />
                </Link>
            </div>
        </template>

        <template #cell(description)="{ item }">
            <div class="space-y-0.5">
                <div>{{ item.name }}</div>
                <div class="text-xs text-gray-500">
                    {{ trans('Packed in') }} {{ formatQuantity(Number(item.units_per_pack) || 1) }}s ,
                    {{ trans('sko/C') }}: {{ formatQuantity(skosPerCarton(item)) }}
                </div>
            </div>
        </template>

        <template #cell(quantity)="{ item }">
            <span class="text-gray-500">{{ quantityBreakdown(item) }}</span>
        </template>

        <template #cell(weight)="{ item }">
            <span v-if="item.weight !== null">{{ locale.number(item.weight) }}Kg</span>
            <FontAwesomeIcon
                v-else
                v-tooltip="trans('Unknown weight')"
                icon="fas fa-exclamation-circle"
                class="text-orange-500"
                aria-hidden="true"
            />
        </template>

        <template #cell(volume)="{ item }">
            <span v-if="item.volume !== null">{{ locale.number(item.volume) }} m³</span>
            <FontAwesomeIcon
                v-else
                v-tooltip="trans('Unknown CBM')"
                icon="fas fa-exclamation-circle"
                class="text-orange-500"
                aria-hidden="true"
            />
        </template>

        <template #cell(amount)="{ item }">
            {{ amount(item) }}
        </template>

        <template #cell(state)="{ item }">
            <div class="flex items-center gap-1.5">
                <FontAwesomeIcon
                    v-tooltip="item.state_icon?.tooltip"
                    :icon="item.state_icon?.icon"
                    :class="item.state_icon?.class"
                    aria-hidden="true"
                    fixed-width
                />
                <span>{{ item.state_label }}</span>

                <button
                    v-if="item.confirmRoute"
                    v-tooltip="trans('Confirm item')"
                    type="button"
                    class="flex items-center justify-center text-emerald-500 hover:text-emerald-700 disabled:text-gray-300"
                    :disabled="changingId === item.id"
                    @click="confirmChangeState($event, item, item.confirmRoute, trans('Confirm this item?'), trans('Confirm'))"
                >
                    <FontAwesomeIcon
                        :icon="changingId === item.id ? 'fas fa-spinner' : 'fal fa-spell-check'"
                        :spin="changingId === item.id"
                        aria-hidden="true"
                        fixed-width
                    />
                </button>

                <button
                    v-else-if="item.readyToShipRoute"
                    v-tooltip="trans('Set ready to ship')"
                    type="button"
                    class="flex items-center justify-center text-indigo-500 hover:text-indigo-700 disabled:text-gray-300"
                    :disabled="changingId === item.id"
                    @click="confirmChangeState($event, item, item.readyToShipRoute, trans('Set this item as ready to ship?'), trans('Ready to ship'))"
                >
                    <FontAwesomeIcon
                        :icon="changingId === item.id ? 'fas fa-spinner' : 'fal fa-box-check'"
                        :spin="changingId === item.id"
                        aria-hidden="true"
                        fixed-width
                    />
                </button>
            </div>
        </template>
    </Table>

    <ConfirmPopup />
</template>
