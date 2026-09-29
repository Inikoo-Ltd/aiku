<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import { bucketQuery } from "@/Composables/bucketQuery"
import Table from "@/Components/Table/Table.vue"
import { TradeUnit } from "@/types/trade-unit"
import Icon from "@/Components/Icon.vue"
import { faSeedling, faScarecrow, faPencil, faSave, faTimes, faSpinnerThird } from "@fal"
import { faCheckCircle, faSkull, faTriangle, faEquals, faMinus } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, computed, ref } from "vue"
import axios from "axios"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureInputDimension from "@/Components/Pure/PureInputDimension.vue"
import Image from "@common/Components/Image.vue"

library.add(faCheckCircle, faSeedling, faSkull, faScarecrow, faTriangle, faEquals, faMinus, faPencil, faSave, faTimes, faSpinnerThird)

const locale = inject("locale", aikuLocaleStructure)

const canEditWeight = computed(() =>
    route().current('grp.trade_units.units.missing_weight')
)

const canEditDimensions = computed(() =>
    route().current('grp.trade_units.units.missing_dimensions')
)

const props = defineProps<{
    data: {}
    tab?: string
    isCheckBox?: boolean
}>()

const emits = defineEmits<{
    (e: 'onSelectTradeUnits', value: TradeUnit[]): void
}>()

const _table = ref<InstanceType<typeof Table> | null>(null)
const selectedTradeUnits = ref<Record<string, TradeUnit>>({})

const onSelectRow = (rows: Record<string, boolean>) => {
    const tradeUnitsOnPage: TradeUnit[] = props.data?.data ?? []

    for (const [tradeUnitId, isSelected] of Object.entries(rows)) {
        if (!isSelected) {
            delete selectedTradeUnits.value[tradeUnitId]
            continue
        }

        const tradeUnit = tradeUnitsOnPage.find((row) => String(row.id) === tradeUnitId)
        if (tradeUnit) {
            selectedTradeUnits.value[tradeUnitId] = tradeUnit
        }
    }

    emits('onSelectTradeUnits', Object.values(selectedTradeUnits.value))
}

const clearSelection = () => {
    _table.value?.clearSelection()
    selectedTradeUnits.value = {}
    emits('onSelectTradeUnits', [])
}

const deselectTradeUnit = (tradeUnitId: number) => {
    const tradeUnitOnPage = (props.data?.data ?? []).find((row: TradeUnit) => row.id === tradeUnitId)
    if (tradeUnitOnPage) {
        tradeUnitOnPage.is_checked = false
    }

    delete selectedTradeUnits.value[tradeUnitId]

    if (_table.value?.selectRow) {
        _table.value.selectRow[tradeUnitId] = false
    } else {
        emits('onSelectTradeUnits', Object.values(selectedTradeUnits.value))
    }
}

defineExpose({
    clearSelection,
    deselectTradeUnit
})

type EditingField = 'net_weight' | 'marketing_weight' | 'marketing_dimensions'
const editingCell = ref<Record<number, EditingField>>({})
const editingNetWeight = ref<Record<number, string | null>>({})
const editingMarketingWeight = ref<Record<number, string | null>>({})
const editingDimensions = ref<Record<number, any>>({})
const loadingSave = ref<number[]>([])
const savedValues = ref<Record<number, Partial<TradeUnit>>>({})

function onEdit(tradeUnit: TradeUnit, field: EditingField) {
    editingCell.value[tradeUnit.id] = field
    if (field === 'net_weight') {
        editingNetWeight.value[tradeUnit.id] = tradeUnit.net_weight ?? null
    } else if (field === 'marketing_weight') {
        editingMarketingWeight.value[tradeUnit.id] = tradeUnit.marketing_weight ?? null
    } else {
        editingDimensions.value[tradeUnit.id] = tradeUnit.marketing_dimensions && Object.keys(tradeUnit.marketing_dimensions).length
            ? { ...tradeUnit.marketing_dimensions }
            : null
    }
}

function onCancel(tradeUnit: TradeUnit) {
    delete editingCell.value[tradeUnit.id]
    delete editingNetWeight.value[tradeUnit.id]
    delete editingMarketingWeight.value[tradeUnit.id]
    delete editingDimensions.value[tradeUnit.id]
}

async function onSave(tradeUnit: TradeUnit) {
    const field = editingCell.value[tradeUnit.id]
    if (!field) return

    const payload: Record<string, any> = {}

    if (field === 'net_weight') {
        const raw = editingNetWeight.value[tradeUnit.id]
        const parsed = Number(raw)
        if (raw === null || raw === '' || !Number.isInteger(parsed) || parsed < 0) return
        payload.net_weight = parsed
    } else if (field === 'marketing_weight') {
        const raw = editingMarketingWeight.value[tradeUnit.id]
        const parsed = Number(raw)
        if (raw === null || raw === '' || !Number.isInteger(parsed) || parsed < 0) return
        payload.marketing_weight = parsed
    } else {
        payload.marketing_dimensions = editingDimensions.value[tradeUnit.id]
    }

    loadingSave.value.push(tradeUnit.id)

    try {
        await axios.patch(route("grp.models.trade-unit.update", { tradeUnit: tradeUnit.id }), payload)
        savedValues.value[tradeUnit.id] = { ...savedValues.value[tradeUnit.id], ...payload }
        delete editingCell.value[tradeUnit.id]
        delete editingNetWeight.value[tradeUnit.id]
        delete editingMarketingWeight.value[tradeUnit.id]
        delete editingDimensions.value[tradeUnit.id]
    } finally {
        loadingSave.value = loadingSave.value.filter(id => id !== tradeUnit.id)
    }
}

const formatDimensions = (dims: any): string => {
    if (!dims || !Object.keys(dims).length) return ''
    const parts = []
    if (dims.l != null) parts.push(`L: ${dims.l}`)
    if (dims.w != null) parts.push(`W: ${dims.w}`)
    if (dims.h != null) parts.push(`H: ${dims.h}`)
    const unit = dims.units ?? ''
    return parts.join(' × ') + (unit ? ` ${unit}` : '')
}

function tradeUnitHref(tradeUnit: TradeUnit) {
    const bucket = route().current()?.match(/^grp\.trade_units\.units\.(in_process|active|discontinuing|discontinued|anomality)$/)?.[1]

    return tradeUnitRoute(tradeUnit) + bucketQuery(bucket)
}

function tradeUnitRoute(tradeUnit: TradeUnit) {
    return route(
        "grp.trade_units.units.show",
        [tradeUnit.slug])
}


const visitBrand = (tradeUnit: TradeUnit) => {
    router.visit(route('grp.trade_units.brands.trade_units.index', {
        brand: tradeUnit.brands?.slug,
    }));
}

const getIntervalChangesIcon = (isPositive: boolean) => {
    if (isPositive) {
        return { icon: faTriangle }
    } else {
        return { icon: faTriangle, class: "rotate-180" }
    }
}

const getIntervalStateColor = (isPositive: boolean) => {
    return isPositive ? "text-green-500" : "text-red-500"
}
</script>

<template>
    <Table ref="_table" :resource="data" :name="tab" class="mt-5" :isCheckBox="isCheckBox" checkboxKey="id" @onSelectRow="onSelectRow">
        <template #cell(status)="{ item: tradeUnit }">
            <Icon :data="tradeUnit.status_icon" />
        </template>
        <template #cell(image_thumbnail)="{ item: tradeUnit }">
            <Image :src="tradeUnit.image_thumbnail" imageCover class="w-6 aspect-square rounded-full overflow-hidden shadow" />
        </template>
        <template #cell(code)="{ item: tradeUnit }">
            <Link :href="tradeUnitHref(tradeUnit) as string" class="primaryLink">
                {{ tradeUnit["code"] }}
            </Link>
        </template>
        <template #cell(name)="{ item: tradeUnit }">
            {{ tradeUnit["name"] }}
        </template>

        <template #cell(net_weight)="{ item: tradeUnit }">
            <div class="flex items-center justify-end gap-2">
                <template v-if="editingCell[tradeUnit.id] === 'net_weight'">
                    <div class="flex items-center gap-1 shrink-0">
                        <div class="w-24">
                            <PureInput v-model="editingNetWeight[tradeUnit.id]" type="number" step="1" min="0" autofocus />
                        </div>
                        <span class="text-gray-500 text-sm">grams</span>
                    </div>
                    <button @click="onSave(tradeUnit)" :disabled="loadingSave.includes(tradeUnit.id)" class="text-green-500 hover:text-green-700 disabled:opacity-50">
                        <FontAwesomeIcon v-if="loadingSave.includes(tradeUnit.id)" icon="fal fa-spinner-third" class="h-5 w-5 animate-spin" fixed-width />
                        <FontAwesomeIcon v-else icon="fal fa-save" class="h-5 w-5" fixed-width />
                    </button>
                    <button @click="onCancel(tradeUnit)" :disabled="loadingSave.includes(tradeUnit.id)" class="text-gray-400 hover:text-gray-600 disabled:opacity-50">
                        <FontAwesomeIcon icon="fal fa-times" class="h-5 w-5" fixed-width />
                    </button>
                </template>
                <template v-else>
                    <span>{{ (savedValues[tradeUnit.id]?.net_weight ?? tradeUnit["net_weight"]) != null ? (savedValues[tradeUnit.id]?.net_weight ?? tradeUnit["net_weight"]) + ' g' : canEditWeight ? '' : '-' }}</span>
                    <button v-if="canEditWeight" @click="onEdit(tradeUnit, 'net_weight')" class="text-gray-400 hover:text-gray-600">
                        <FontAwesomeIcon icon="fal fa-pencil" class="h-3.5 w-3.5" fixed-width />
                    </button>
                </template>
            </div>
        </template>

        <template #cell(marketing_weight)="{ item: tradeUnit }">
            <div class="flex items-center justify-end gap-2">
                <template v-if="editingCell[tradeUnit.id] === 'marketing_weight'">
                    <div class="flex items-center gap-1 shrink-0">
                        <div class="w-24">
                            <PureInput v-model="editingMarketingWeight[tradeUnit.id]" type="number" step="1" min="0" autofocus />
                        </div>
                        <span class="text-gray-500 text-sm">grams</span>
                    </div>
                    <button @click="onSave(tradeUnit)" :disabled="loadingSave.includes(tradeUnit.id)" class="text-green-500 hover:text-green-700 disabled:opacity-50">
                        <FontAwesomeIcon v-if="loadingSave.includes(tradeUnit.id)" icon="fal fa-spinner-third" class="h-5 w-5 animate-spin" fixed-width />
                        <FontAwesomeIcon v-else icon="fal fa-save" class="h-5 w-5" fixed-width />
                    </button>
                    <button @click="onCancel(tradeUnit)" :disabled="loadingSave.includes(tradeUnit.id)" class="text-gray-400 hover:text-gray-600 disabled:opacity-50">
                        <FontAwesomeIcon icon="fal fa-times" class="h-5 w-5" fixed-width />
                    </button>
                </template>
                <template v-else>
                    <span>{{ (savedValues[tradeUnit.id]?.marketing_weight ?? tradeUnit["marketing_weight"]) != null ? (savedValues[tradeUnit.id]?.marketing_weight ?? tradeUnit["marketing_weight"]) + ' g' : canEditWeight ? '' : '-' }}</span>
                    <button v-if="canEditWeight" @click="onEdit(tradeUnit, 'marketing_weight')" class="text-gray-400 hover:text-gray-600">
                        <FontAwesomeIcon icon="fal fa-pencil" class="h-3.5 w-3.5" fixed-width />
                    </button>
                </template>
            </div>
        </template>

        <template #cell(marketing_dimensions)="{ item: tradeUnit }">
            <div class="flex items-center justify-end gap-2">
                <template v-if="editingCell[tradeUnit.id] === 'marketing_dimensions'">
                    <div class="shrink-0">
                        <PureInputDimension v-model="editingDimensions[tradeUnit.id]" />
                    </div>
                    <button @click="onSave(tradeUnit)" :disabled="loadingSave.includes(tradeUnit.id)" class="text-green-500 hover:text-green-700 disabled:opacity-50">
                        <FontAwesomeIcon v-if="loadingSave.includes(tradeUnit.id)" icon="fal fa-spinner-third" class="h-5 w-5 animate-spin" fixed-width />
                        <FontAwesomeIcon v-else icon="fal fa-save" class="h-5 w-5" fixed-width />
                    </button>
                    <button @click="onCancel(tradeUnit)" :disabled="loadingSave.includes(tradeUnit.id)" class="text-gray-400 hover:text-gray-600 disabled:opacity-50">
                        <FontAwesomeIcon icon="fal fa-times" class="h-5 w-5" fixed-width />
                    </button>
                </template>
                <template v-else>
                    <span>{{ savedValues[tradeUnit.id]?.marketing_dimensions ? formatDimensions(savedValues[tradeUnit.id]?.marketing_dimensions ?? tradeUnit["marketing_dimensions"]) : canEditDimensions ? '' : '-'}}</span>
                    <button v-if="canEditDimensions" @click="onEdit(tradeUnit, 'marketing_dimensions')" class="text-gray-400 hover:text-gray-600">
                        <FontAwesomeIcon icon="fal fa-pencil" class="h-3.5 w-3.5" fixed-width />
                    </button>
                </template>
            </div>
        </template>

        <template #cell(type)="{ item: tradeUnit }">
            <div class="capitalize">{{ tradeUnit["type"] }}</div>
        </template>
        <template #cell(units)="{ item: tradeUnit }">
            {{ tradeUnit["units"] }}
        </template>

        <template #cell(sales_grp_currency_external)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.grp_currency, item.sales_grp_currency_external) }}</span>
        </template>

        <template #cell(sales_grp_currency_external_delta)="{ item }">
            <div v-if="item.sales_grp_currency_external_delta">
                <span>{{ item.sales_grp_currency_external_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive).class,
                        getIntervalStateColor(item.sales_grp_currency_external_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faEquals" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
            </div>
        </template>

        <template #cell(invoices)="{ item }">
            <span class="tabular-nums">{{ item.invoices }}</span>
        </template>

        <template #cell(invoices_delta)="{ item }">
            <div v-if="item.invoices_delta">
                <span>{{ item.invoices_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.invoices_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.invoices_delta.is_positive).class,
                        getIntervalStateColor(item.invoices_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faEquals" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
            </div>
        </template>

        <template #cell(brands)="{ item }">
            <span
                v-if="item.brands?.name"
                v-tooltip="'Click to go to Brand'"
                class="border border-gray-400 bg-gray-200 rounded-md px-2 py-1 font-light cursor-pointer hover:opacity-[80%] transition ease-in-out whitespace-nowrap"
                @click="visitBrand(item)"
            >
                {{ item.brands?.name }}
            </span>
            <span v-else />
        </template>

        <template #cell(tags)="{ item }">
            <div class="flex gap-x-1 gap-y-1 flex-wrap">
                <span
                    v-for="tag in item.tags"
                    :style="'background-color:'+tag.class_color"
                    class="px-2 py-1 border rounded-md text-white"
                >
                    {{ tag.name }}
                </span>
                <span v-if="!item.tags.length" />
            </div>
        </template>
    </Table>
</template>
