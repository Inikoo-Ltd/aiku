<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { TradeUnit } from "@/types/trade-unit"
import Icon from "@/Components/Icon.vue"
import { faSeedling, faScarecrow, faPencil, faSave, faTimes } from "@fal"
import { faCheckCircle, faSkull, faTriangle, faEquals, faMinus } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, computed, ref } from "vue"
import axios from "axios"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureInputDimension from "@/Components/Pure/PureInputDimension.vue"

library.add(faCheckCircle, faSeedling, faSkull, faScarecrow, faTriangle, faEquals, faMinus, faPencil, faSave, faTimes)

const locale = inject("locale", aikuLocaleStructure)
const showEditActions = computed(() =>
    route().current('grp.trade_units.units.missing_weight') ||
    route().current('grp.trade_units.units.missing_dimensions')
)

defineProps<{
    data: {}
    tab?: string
}>()

type EditingField = 'marketing_weight' | 'marketing_dimensions'
const editingCell = ref<Record<number, EditingField>>({})
const editingMarketingWeight = ref<Record<number, string | null>>({})
const editingDimensions = ref<Record<number, any>>({})
const loadingSave = ref<number[]>([])

function onEdit(tradeUnit: TradeUnit, field: EditingField) {
    editingCell.value[tradeUnit.id] = field
    if (field === 'marketing_weight') {
        editingMarketingWeight.value[tradeUnit.id] = tradeUnit.marketing_weight ?? null
    } else {
        editingDimensions.value[tradeUnit.id] = tradeUnit.marketing_dimensions && Object.keys(tradeUnit.marketing_dimensions).length
            ? { ...tradeUnit.marketing_dimensions }
            : null
    }
}

function onCancel(tradeUnit: TradeUnit) {
    delete editingCell.value[tradeUnit.id]
    delete editingMarketingWeight.value[tradeUnit.id]
    delete editingDimensions.value[tradeUnit.id]
}

function onSave(tradeUnit: TradeUnit) {
    const field = editingCell.value[tradeUnit.id]
    if (!field) return

    const payload: Record<string, any> = {}

    if (field === 'marketing_weight') {
        const raw = editingMarketingWeight.value[tradeUnit.id]
        const parsed = Number(raw)
        if (raw === null || raw === '' || !Number.isInteger(parsed) || parsed < 0) return
        payload.marketing_weight = parsed
    } else {
        payload.marketing_dimensions = editingDimensions.value[tradeUnit.id]
    }

    router.patch(
        route("grp.models.trade-unit.update", { tradeUnit: tradeUnit.id }),
        payload,
        {
            preserveScroll: true,
            onStart: () => loadingSave.value.push(tradeUnit.id),
            onSuccess: () => {
                Object.assign(tradeUnit, payload)
                delete editingCell.value[tradeUnit.id]
                delete editingMarketingWeight.value[tradeUnit.id]
                delete editingDimensions.value[tradeUnit.id]
            },
            onFinish: () => {
                loadingSave.value = loadingSave.value.filter(id => id !== tradeUnit.id)
            },
        }
    )
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
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(status)="{ item: tradeUnit }">
            <Icon :data="tradeUnit.status_icon" />
        </template>
        <template #cell(code)="{ item: tradeUnit }">
            <Link :href="tradeUnitRoute(tradeUnit) as string" class="primaryLink">
                {{ tradeUnit["code"] }}
            </Link>
        </template>
        <template #cell(name)="{ item: tradeUnit }">
            {{ tradeUnit["name"] }}
        </template>

        <template #cell(marketing_weight)="{ item: tradeUnit }">
            <div class="flex items-center justify-end gap-2">
                <template v-if="editingCell[tradeUnit.id] === 'marketing_weight'">
                    <div class="flex items-center gap-1 shrink-0">
                        <div class="w-24">
                            <PureInput v-model="editingMarketingWeight[tradeUnit.id]" type="number" step="1" min="0" autofocus />
                        </div>
                        <span class="text-gray-500 text-sm">gram</span>
                    </div>
                    <button @click="onSave(tradeUnit)" :disabled="loadingSave.includes(tradeUnit.id)" class="text-green-500 hover:text-green-700">
                        <FontAwesomeIcon icon="fal fa-save" class="h-5 w-5" />
                    </button>
                    <button @click="onCancel(tradeUnit)" class="text-gray-400 hover:text-gray-600">
                        <FontAwesomeIcon icon="fal fa-times" class="h-5 w-5" />
                    </button>
                </template>
                <template v-else>
                    <span>{{ tradeUnit["marketing_weight"] != null ? tradeUnit["marketing_weight"] + ' g' : '' }}</span>
                    <button v-if="showEditActions" @click="onEdit(tradeUnit, 'marketing_weight')" class="text-gray-400 hover:text-gray-600">
                        <FontAwesomeIcon icon="fal fa-pencil" class="h-3.5 w-3.5" />
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
                    <button @click="onSave(tradeUnit)" :disabled="loadingSave.includes(tradeUnit.id)" class="text-green-500 hover:text-green-700">
                        <FontAwesomeIcon icon="fal fa-save" class="h-5 w-5" />
                    </button>
                    <button @click="onCancel(tradeUnit)" class="text-gray-400 hover:text-gray-600">
                        <FontAwesomeIcon icon="fal fa-times" class="h-5 w-5" />
                    </button>
                </template>
                <template v-else>
                    <span>{{ tradeUnit["marketing_dimensions"] && Object.keys(tradeUnit["marketing_dimensions"]).length ? JSON.stringify(tradeUnit["marketing_dimensions"]) : '' }}</span>
                    <button v-if="showEditActions" @click="onEdit(tradeUnit, 'marketing_dimensions')" class="text-gray-400 hover:text-gray-600">
                        <FontAwesomeIcon icon="fal fa-pencil" class="h-3.5 w-3.5" />
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
            <span class="tabular-nums">{{ locale.currencyFormat('GBP', item.sales_grp_currency_external) }}</span>
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
