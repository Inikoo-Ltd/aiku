<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import Table from '@/Components/Table/Table.vue'
import Image from '@common/Components/Image.vue'
import NumberWithButtonSave from '@/Components/NumberWithButtonSave.vue'
import { useLocaleStore } from '@/Stores/locale'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBox, faPallet, faStopCircle, faTrashAlt } from '@fal'
import { faExclamationCircle, faSpinner } from '@fas'
import ConfirmPopup from 'primevue/confirmpopup'
import { useConfirm } from 'primevue/useconfirm'

library.add(faBox, faPallet, faStopCircle, faExclamationCircle, faTrashAlt, faSpinner)

const confirm = useConfirm()

const props = defineProps<{
    data: object
    tab?: string
    state?: string
}>()

const locale = useLocaleStore()

type Level = 'cartons' | 'skos' | 'units'

const isInProcess = computed(() => props.state === 'in_process')

const levels = computed(() => [
    { key: 'cartons' as Level, icon: 'fal fa-pallet', tab: trans('Ordering Cartons'), description: trans('Carton description'), quantity: trans('Cartons'), cost: trans('Carton cost') },
    { key: 'skos' as Level, icon: 'fal fa-box', tab: trans('Ordering SKOs'), description: trans('SKO description'), quantity: trans('SKOs'), cost: trans('SKO cost') },
    { key: 'units' as Level, icon: 'fal fa-stop-circle', tab: trans('Ordering Units'), description: trans('Unit description'), quantity: trans('Units'), cost: trans('Unit cost') },
])

const currentLevel = ref<Level>('cartons')

const level = computed(() => levels.value.find(l => l.key === currentLevel.value) ?? levels.value[0])

function unitsPerLevel(item: any) {
    if (currentLevel.value === 'cartons') {
        return Number(item.units_per_carton) || 1
    }
    if (currentLevel.value === 'skos') {
        return Number(item.units_per_pack) || 1
    }

    return 1
}

function skosPerCarton(item: any) {
    const pack = Number(item.units_per_pack) || 1
    const carton = Number(item.units_per_carton) || 1

    return carton / pack
}

function formatQuantity(value: number) {
    return locale.number(Math.round(value * 1000) / 1000)
}

function quantityAtLevel(item: any) {
    return Number(item.quantity_ordered) / unitsPerLevel(item)
}

function levelCost(item: any) {
    return Number(item.unit_cost) * unitsPerLevel(item)
}

function levelCostLabel(item: any) {
    const supplier = locale.currencyFormat(item.net_currency ?? 'EUR', levelCost(item))

    if (!item.org_currency || item.org_currency === item.net_currency) {
        return supplier
    }

    const orgCost = levelCost(item) * (Number(item.org_exchange) || 1)

    return `${supplier} (${locale.currencyFormat(item.org_currency, orgCost)})`
}

function quantityBreakdown(item: any) {
    const units = Number(item.quantity_ordered)
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

const savingId = ref<number | null>(null)

async function onSaveQuantity(item: any, form: any) {
    const quantityOrdered = Number(form.quantity) * unitsPerLevel(item)
    const saveRoute = item.saveRoute ?? item.updateRoute
    const method = String(saveRoute?.method ?? 'patch').toLowerCase()

    savingId.value = item.id
    try {
        await axios[method](
            route(saveRoute.name, saveRoute.parameters),
            { quantity_ordered: quantityOrdered }
        )
        form.defaults()
        notify({ title: trans('Success'), text: trans('Quantity updated'), type: 'success' })
        router.reload({ only: [props.tab ?? 'items', 'box_stats'] })
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error?.response?.data?.message || trans('Failed to update quantity'),
            type: 'error',
        })
    } finally {
        savingId.value = null
    }
}

const deletingId = ref<number | null>(null)

function confirmDeleteItem(event: MouseEvent, item: any) {
    if (!item.deleteRoute) {
        return
    }

    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: trans('Remove this product from the purchase order?'),
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: trans('Delete'),
        rejectLabel: trans('Cancel'),
        acceptClass: 'p-button-danger',
        rejectClass: 'p-button-text',
        accept: () => onDeleteItem(item),
    })
}

async function onDeleteItem(item: any) {
    deletingId.value = item.id
    try {
        await axios.delete(route(item.deleteRoute.name, item.deleteRoute.parameters))
        notify({ title: trans('Success'), text: trans('Item removed'), type: 'success' })
        router.reload({ only: [props.tab ?? 'items', 'box_stats'] })
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error?.response?.data?.message || trans('Failed to remove item'),
            type: 'error',
        })
    } finally {
        deletingId.value = null
    }
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
        <template v-if="isInProcess" #before-table>
            <div class="flex items-end gap-1 border-b border-gray-200 px-3 sm:px-4">
                <button
                    v-for="item in levels"
                    :key="item.key"
                    type="button"
                    class="px-3 py-1.5 text-sm border-b-2 -mb-px transition"
                    :class="item.key === currentLevel
                        ? 'border-indigo-500 text-indigo-600 font-medium'
                        : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="currentLevel = item.key"
                >
                    <FontAwesomeIcon :icon="item.icon" aria-hidden="true" fixed-width />
                    {{ item.tab }}
                </button>
            </div>
        </template>

        <template #header(description)="{ header }">
            <th class="font-normal px-6 w-auto text-left">
                {{ isInProcess ? level.description : header.label }}
            </th>
        </template>

        <template #header(quantity)="{ header }">
            <th class="font-normal px-6 w-auto" :class="isInProcess ? 'text-right' : 'text-left'">
                {{ isInProcess ? level.quantity : header.label }}
            </th>
        </template>

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

        <template #cell(image_thumbnail)="{ item }">
            <div class="flex">
                <Image :src="item['image_thumbnail']" imageCover class="w-20 aspect-square overflow-hidden" />
            </div>
        </template>

        <template #cell(description)="{ item }">
            <div class="space-y-0.5">
                <div>
                    <span v-if="isInProcess && currentLevel !== 'units'" class="font-medium">
                        {{ formatQuantity(unitsPerLevel(item)) }}x
                    </span>
                    {{ item.name }}
                </div>
                <div v-if="isInProcess" class="text-xs text-gray-500">
                    {{ level.cost }}: {{ levelCostLabel(item) }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ trans('Packed in') }} {{ formatQuantity(Number(item.units_per_pack) || 1) }}s ,
                    {{ trans('sko/C') }}: {{ formatQuantity(skosPerCarton(item)) }}
                </div>
            </div>
        </template>

        <template #cell(subtotals)="{ item }">
            <div class="space-y-0.5">
                <div class="text-gray-500">{{ quantityBreakdown(item) }}</div>
                <div class="flex items-center gap-1.5">
                    <span>{{ amount(item) }}</span>
                    <span v-if="item.weight !== null" class="text-gray-500">
                        {{ locale.number(item.weight) }}Kg
                    </span>
                    <FontAwesomeIcon
                        v-else
                        v-tooltip="trans('Unknown weight')"
                        icon="fas fa-exclamation-circle"
                        class="text-orange-500"
                        aria-hidden="true"
                    />
                </div>
            </div>
        </template>

        <template #cell(quantity)="{ item }">
            <div v-if="isInProcess" class="flex justify-end items-center gap-2">
                <NumberWithButtonSave
                    :key="`${item.id}-${currentLevel}`"
                    :modelValue="quantityAtLevel(item)"
                    :min="0"
                    :isLoading="savingId === item.id"
                    @onSave="(form) => onSaveQuantity(item, form)"
                />
                <button
                    v-if="item.deleteRoute"
                    v-tooltip="trans('Remove')"
                    type="button"
                    class="flex items-center justify-center text-gray-400 hover:text-red-500 disabled:text-gray-300"
                    :disabled="deletingId === item.id"
                    @click="confirmDeleteItem($event, item)"
                >
                    <FontAwesomeIcon
                        :icon="deletingId === item.id ? 'fas fa-spinner' : 'fal fa-trash-alt'"
                        :spin="deletingId === item.id"
                        aria-hidden="true"
                        fixed-width
                    />
                </button>
            </div>
            <span v-else class="text-gray-500">{{ quantityBreakdown(item) }}</span>
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
            </div>
        </template>
    </Table>

    <ConfirmPopup />
</template>
