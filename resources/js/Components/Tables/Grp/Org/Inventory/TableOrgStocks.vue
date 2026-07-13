<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 24 Mar 2024 21:09:00 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, useForm } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import Table from "@/Components/Table/Table.vue"
import { Stock } from "@/types/stock"
import { computed, inject, ref } from "vue"
import Dialog from "primevue/dialog"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Icon from "@/Components/Icon.vue"
import { faCheckCircle, faTimesCircle, faPauseCircle, faExclamationCircle, faTriangle, faEquals, faMinus } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { RouteParams } from "@/types/route-params"
import { OrgStock } from "@/types/org-stock"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import { faForklift, faCheck, faHandPaper } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import PureCheckbox from '@/Components/Pure/PureCheckbox.vue'
import { ctrans } from "@/Composables/useTrans"

library.add(faCheckCircle, faTimesCircle, faPauseCircle, faExclamationCircle, faTriangle, faEquals, faMinus)

const props = defineProps<{
    data: object
    tab?: string
    canMoveAllSku?:boolean,
    location_id: number,
}>()

const locale = inject("locale", aikuLocaleStructure)
const isOpenMoveAllSku = ref(false)
const form = useForm({
    location_id: null,
    remove_after_move : false
})

interface PartialMoveRow {
    org_stock_id: number
    code: string
    name: string
    available: number
    quantity_to_move: number
    remove_after_move: boolean
}

function onCancelPartialMoveSku() {
    isOpenPartialMove.value = false
}

const isOpenPartialMove = ref(false)
const selectedRows = ref<Record<string, boolean>>({})

const selectedStocks = computed(() => {
    const rows = (props.data as { data?: OrgStock[] })?.data ?? []
    return rows.filter((row) => selectedRows.value[row.id])
})

const hasSelection = computed(() => selectedStocks.value.length > 0)

function onSelectRow(value: Record<string, boolean>) {
    selectedRows.value = { ...value }
}

const partialForm = useForm<{ location_id: number | null; org_stocks: PartialMoveRow[] }>({
    location_id: null,
    org_stocks: [],
})

const isPartialMoveValid = computed(() => {
    if (!partialForm.location_id || partialForm.org_stocks.length === 0) {
        return false
    }

    return partialForm.org_stocks.every((row) => Number(row.quantity_to_move) > 0 && Number(row.quantity_to_move) <= row.available)
})

function openPartialMoveSku() {
    partialForm.reset()
    partialForm.location_id = null
    partialForm.org_stocks = selectedStocks.value.map((stock) => ({
        org_stock_id: stock.id,
        code: stock.code,
        name: stock.name,
        available: Number(stock.quantity),
        quantity_to_move: Number(stock.quantity),
        remove_after_move: false,
    }))
    isOpenPartialMove.value = true
}

function onToggleRemoveAfterMove(row: PartialMoveRow) {
    if (row.remove_after_move) {
        row.quantity_to_move = row.available
    }
}

function onSavePartialMoveSku() {
    const params = route().params as RouteParams
    console.log(partialForm)
    partialForm
        .transform((data) => ({
            location_id: data.location_id,
            org_stocks: data.org_stocks.map((row) => ({
                org_stock_id: row.org_stock_id,
                quantity: row.quantity_to_move,
                remove_after_move: row.remove_after_move,
            })),
        }))
        .post(
            route("grp.models.location.partial_move_stock", {
                location: props.location_id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    isOpenPartialMove.value = false
                    partialForm.reset()
                    selectedRows.value = {}
                    notify({
                        title: ctrans("Success"),
                        text: ctrans("SKU moved successfully"),
                        type: "success",
                    })
                },
                onError: () => {
                    notify({
                        title: ctrans("Something went wrong"),
                        text: ctrans("Failed to move SKU"),
                        type: "error",
                    })
                },
            }
        )
}

function onSaveMoveAllSku() {

    form.post(
        route("grp.models.location.mass_move_stock", {
            location: props.location_id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                isOpenMoveAllSku.value = false
                form.reset()
                notify({
                    title: ctrans("Success"),
                    text: ctrans("All SKU moved successfully"),
                    type: "success",
                })
            },
            onError: () => {
                notify({
                    title: ctrans("Something went wrong"),
                    text: ctrans("Failed to move all SKU"),
                    type: "error",
                })
            },
        }
    )
}

function onCancelMoveAllSku() {
    isOpenMoveAllSku.value = false
}

function orgStockRoute(orgStock: OrgStock) {
    const current = route().current()
    console.log(current)

    if (current === "grp.org.warehouses.show.inventory.org_stock_families.show") {
        return route(
            "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).warehouse,
                (route().params as RouteParams).orgStockFamily,
                orgStock.slug
            ]
        )
    } else if (current === "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index") {
        return route(
            "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).warehouse,
                orgStock.slug
            ]
        )
    } else if (current === "grp.overview.inventory.org-stocks.index" || current === "grp.org.shops.show.catalogue.products.all_products.show") {
        return route(
            "grp.helpers.redirect_org_stock",
            [orgStock.id])
    } else if (current === "grp.org.warehouses.show.inventory.org_stocks.index") {
        return route(
            "grp.org.warehouses.show.inventory.org_stocks.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).warehouse,
                orgStock.slug
            ]
        )
    } else if (current === "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.index") {
        return route(
            "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).warehouse,
                orgStock.slug
            ]
        )
    }else{
      return route(
            "grp.helpers.redirect_org_stock",
            [
                orgStock.id
            ]
        )
    }
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

function stockFamilyRoute(stock: Stock) {
    return route(
        "grp.org.warehouses.show.inventory.org_stock_families.show",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).warehouse,
            stock.family_slug
        ]
    )
}


const orgStockRouteProductIndex = (orgStock: OrgStock) => {
    const current = route().current()

    if (current === "grp.org.warehouses.show.inventory.org_stock_families.show") {
        return route(
            "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show.products",
            {
                organisation: (route().params as RouteParams).organisation,
                warehouse: (route().params as RouteParams).warehouse,
                orgStockFamily: (route().params as RouteParams).orgStockFamily,
                orgStock: orgStock.slug,
                tab: 'products'
            }
        )
    } else if (current === "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index") {
        return route(
            "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show.products",
            {
                organisation: (route().params as RouteParams).organisation,
                warehouse: (route().params as RouteParams).warehouse,
                orgStock: orgStock.slug,
                tab: 'products'
            }
        )
    } else if (current === "grp.overview.inventory.org-stocks.index" || current === "grp.org.shops.show.catalogue.products.all_products.show") {
        return route(
            "grp.helpers.redirect_org_stock",
            [orgStock.id])
    } else if (current === "grp.org.warehouses.show.inventory.org_stocks.index") {
        return route(
            "grp.org.warehouses.show.inventory.org_stocks.show.products",
            {
                organisation: (route().params as RouteParams).organisation,
                warehouse: (route().params as RouteParams).warehouse,
                orgStock: orgStock.slug,
                tab: 'products'
            }
        )
    } else if (current === "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.index") {
        return route(
            "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show.products",
            {
                organisation: (route().params as RouteParams).organisation,
                warehouse: (route().params as RouteParams).warehouse,
                orgStock: orgStock.slug,
                tab: 'products'
            }
        )
    }else{
      return route(
            "grp.helpers.redirect_org_stock.to_products_index",
            [
                orgStock.id
            ]
        )
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="canMoveAllSku" @onSelectRow="onSelectRow">
          <template #add-on-button v-if="canMoveAllSku">
                <Button :label="ctrans('Move All SKU')" type="white" :icon="faForklift" size="xs" @click="isOpenMoveAllSku = true"></Button>
                <Button v-if="hasSelection" :label="ctrans('Partialy Move SKU')" type="white" :icon="faForklift" size="xs" @click="openPartialMoveSku"></Button>
          </template>
        <template #cell(state)="{ item: stock }">
            <Icon :data="stock.state"></Icon>
        </template>
        <template #cell(type)="{ item: stock }">
            <FontAwesomeIcon v-if="stock.type" :icon="stock.type == 'picking' ? faCheck : faHandPaper  " :data="stock.type"></FontAwesomeIcon>
        </template>
        <template #cell(org_sku)="{ item: stock }">
            <Link :href="orgStockRoute(stock) as string" class="primaryLink">
                {{ stock["organisation_code"] }}
            </Link>
        </template>

        <template #cell(code)="{ item: stock }">
            <Link :href="orgStockRoute(stock) as string" class="primaryLink">
                {{ stock["code"] }}
            </Link>
        </template>
        <template #cell(name)="{ item: stock }">
            <div class="flex gap-3">
                {{ stock["name"] }} <span v-if="stock?.is_on_demand"
												class="text-[10px] px-1.5 rounded bg-amber-100 text-amber-700">
												On Demand
											</span>
            </div>
        </template>
        <template #cell(family_code)="{ item: stock }">
            <!--suppress TypeScriptUnresolvedReference -->
            <Link v-if="stock.family_slug" :href="stockFamilyRoute(stock)" class="secondaryLink">
                {{ stock["family_code"] }}
            </Link>
        </template>
        <!-- <template #cell(type)="{ item: stock }">
            {{ stock.type ?? "" }}
        </template> -->

        <template #cell(picking_priority)="{ item: stock }">
            {{ stock.picking_priority ?? "" }}
        </template>

        <template #cell(value)="{ item: stock }">
            {{ locale.currencyFormat(stock.currency_code, stock.value) }}
        </template>

        <template #cell(dropshipping_pipe)="{ item: stock }">
            {{ stock.dropshipping_pipe ?? "" }}
        </template>

        <template #cell(quantity)="{ item: stock }">
            <div class="text-right">
                <FractionDisplay v-if="stock.pick_fractional?.length > 0" :fractionData="stock.pick_fractional"/>
                <span v-else>
                    {{ stock.quantity }}
                </span>
            </div>
        </template>

        <template #cell(notes)="{ item: stock }">
            {{ stock.notes ?? "" }}
        </template>

        <template #cell(woc)="{ item }">
            <span v-if="item.woc !== null" class="tabular-nums">{{ item.woc }}w</span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(unit_cost)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.currency_code, item.unit_cost) }}</span>
        </template>

        <template #cell(on_the_way_po_value)="{ item }">
            <span class="tabular-nums">
                {{ locale.currencyFormat(item.currency_code, item.on_the_way_po_value) }}
                <span v-if="item.on_the_way_po_count > 0" class="text-gray-400">({{ item.on_the_way_po_count }})</span>
            </span>
        </template>

         <template #cell(stock_value)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.currency_code, item.stock_value) }}</span>
        </template>

        <template #cell(sales_grp_currency_external)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.currency_code, item.sales_grp_currency_external) }}</span>
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

        <template #cell(product_count)="{ item }">
            <Link :href="orgStockRouteProductIndex(item) as string" class="primaryLink">
                {{ item.product_count }}
            </Link>
        </template>

        <template #cell(quantity_in_locations)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.quantity_in_locations) }}</span>
        </template>

        <template #cell(org_stock_value)="{ item }">
            <span v-if="item.org_stock_value">{{ locale.currencyFormat(item.currency_code, item.org_stock_value) }}</span>
        </template>

        <template #cell(sold_within_1y)="{ item }">
            <Icon :data="item.sold_within_1y" />
        </template>

        <template #cell(non_moving_1y)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.non_moving_1y) }}</span>
        </template>



    </Table>

    <Dialog
        :header="ctrans('Move All SKU')"
        v-model:visible="isOpenMoveAllSku"
        modal
        closable
        :content-style="{ width: '500px', overflow : 'visible' }"
    >
        <div class="px-2">
            <PureMultiselectInfiniteScroll
                mode="single"
                v-model="form.location_id"
                :fetchRoute="{
                    name: 'grp.org.warehouses.show.infrastructure.locations.index',
                    parameters: {
                        organisation: (route().params as RouteParams).organisation,
                        warehouse: (route().params as RouteParams).warehouse,
                    },
                }"
                valueProp="id"
                labelProp="code"
                :placeholder="ctrans('Select a location')"
            />

            <label class="mt-4 flex items-center gap-2 cursor-pointer">
                <PureCheckbox v-model="form.remove_after_move" />
                <span>{{ ctrans('Remove after move') }}</span>
            </label>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button type="cancel" :label="ctrans('Cancel')" @click="onCancelMoveAllSku" />
                <Button type="save" :label="ctrans('Save')" :loading="form.processing" :disabled="!form.location_id" @click="onSaveMoveAllSku" />
            </div>
        </template>
    </Dialog>

    <Dialog
        :header="ctrans('Partialy Move SKU')"
        v-model:visible="isOpenPartialMove"
        modal
        closable
        :content-style="{ width: '720px', overflow: 'visible' }"
    >
        <div class="px-2 space-y-4">
            <div>
                <label class="block mb-1 text-sm text-gray-600">{{ ctrans('Move to location') }}</label>
                <PureMultiselectInfiniteScroll
                    mode="single"
                    v-model="partialForm.location_id"
                    :fetchRoute="{
                        name: 'grp.org.warehouses.show.infrastructure.locations.index',
                        parameters: {
                            organisation: (route().params as RouteParams).organisation,
                            warehouse: (route().params as RouteParams).warehouse,
                        },
                    }"
                    valueProp="id"
                    labelProp="code"
                    :placeholder="ctrans('Select a location')"
                />
            </div>

            <DataTable :value="partialForm.org_stocks" class="border border-gray-200 rounded-md" scrollable scrollHeight="360px">
                <Column field="code" :header="ctrans('Reference')">
                    <template #body="{ data }">
                        <div class="flex flex-col">
                            <span class="font-medium">{{ data.code }}</span>
                            <span class="text-xs text-gray-500">{{ data.name }}</span>
                        </div>
                    </template>
                </Column>
                <Column :header="ctrans('Available')">
                    <template #body="{ data }">
                        <span class="tabular-nums">{{ data.available }}</span>
                    </template>
                </Column>
                <Column :header="ctrans('Quantity to move')">
                    <template #body="{ data }">
                        <PureInputNumber
                            v-model="data.quantity_to_move"
                            :minValue="0"
                            :maxValue="data.available"
                        />
                    </template>
                </Column>
                <Column :header="ctrans('Remove after move')">
                    <template #body="{ data }">
                        <div class="flex justify-center">
                            <PureCheckbox v-model="data.remove_after_move" @update:modelValue="onToggleRemoveAfterMove(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button type="cancel" :label="ctrans('Cancel')" @click="onCancelPartialMoveSku" />
                <Button type="save" :label="ctrans('Save')" :loading="partialForm.processing" :disabled="!isPartialMoveValid" @click="onSavePartialMoveSku" />
            </div>
        </template>
    </Dialog>
</template>
