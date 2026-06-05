<script setup lang="ts">
import { ref, reactive, inject, onBeforeUnmount, computed } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import Table from "@/Components/Table/Table.vue"
import Tag from "@/Components/Tag.vue"
import { routeType } from "@/types/route"
import { Table as TableTS } from "@/types/Table"
import { faPencil, faTimes, faTrashAlt, faMoneyCheckEditAlt, faPlus, faMinus } from "@far"
import { faBarcode } from "@fal"
import { Link, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { debounce, get, set } from "lodash-es"
import Modal from "@/Components/Utils/Modal.vue"
import ProductsSelectorAutoSelect from "@/Components/Dropshipping/ProductsSelectorAutoSelect.vue"
import { ulid } from "ulid"
import Image from "@common/Components/Image.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBadgePercent, faFragile } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import Discount from "@/Components/Utils/Label/Discount.vue"
import { InputNumber, InputText } from "primevue"
import axios from "axios"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import BasicDiscount from "@/Components/Utils/Label/DiscountTemplate/BasicDiscount.vue"
import error from "@iris/Pages/Errors/Error.vue"

library.add(faBadgePercent, faFragile, faMoneyCheckEditAlt, faBarcode)

type ProductRow = {
    id: number
    asset_code: string
    asset_name: string
    quantity_ordered: number
    available_quantity?: number
    product_slug?: string
    updateRoute: routeType
    deleteRoute?: routeType
}

const props = defineProps<{
    data: ProductRow[] | TableTS<ProductRow>
    tab: string
    updateRoute: routeType
    state?: string
    readonly?: boolean
    modifyRoute?: routeType
    fetchRoute?: routeType
    routesProductsListModification?: routeType
    is_shop_external: boolean
}>()

const layout = inject("layout", {})
const locale = inject("locale", {})
const editingIds = ref<Set<number>>(new Set())
const createNewQty = reactive<Record<number, ProductRow>>({})
const isLoading = ref<string | null>(null)
const isModalProductListOpen = ref(false)
const loadingsaveModify = ref(false)
const currentAction = ref(null)

// Helper: get rows as array
function rowsArray() {
    if (Array.isArray(props.data)) return props.data
    return (props.data as TableTS<ProductRow>).data || []
}

// --- Utils ---
function formatQuantity(value: any): string | number {
    if (Number.isInteger(Number(value)) && String(value).match(/^\d+(\.0+)?$/)) {
        return parseInt(value)
    }
    return parseFloat(value)
}

function productRoute(product: ProductRow) {
    switch (route().current()) {
        case "grp.org.shops.show.crm.customers.show.orders.show":
        case "grp.org.shops.show.ordering.orders.show":
            if (product.product_slug) {
                return route(
                    "grp.org.shops.show.catalogue.products.all_products.show",
                    [
                        route().params["organisation"],
                        route().params["shop"],
                        product.product_slug
                    ]
                )
            }
            return ""
        default:
            return ""
    }
}

// --- Editing Logic ---
function startEdit(item: ProductRow) {
    if (!editingIds.value.has(item.id)) {
        editingIds.value.add(item.id)
        createNewQty[item.id] = { ...item }
    }
}

function onCancel(item: ProductRow) {
    delete createNewQty[item.id]
    editingIds.value.delete(item.id)
}

// --- Update Logic ---
const onUpdateQuantity = (
    routeUpdate: routeType,
    idTransaction: number,
    value: number,
    is_cut_view: boolean
) => {
    let sendData = is_cut_view ? {
        units_ordered: Number(value)
    } : {
        quantity_ordered: Number(value)
    }
    router.patch(
        route(routeUpdate.name, routeUpdate.parameters),
        sendData,
        {
            onError: (e: any) => {
                notify({
                    title: trans("Something went wrong"),
                    text: e.message,
                    type: "error"
                })
            },
            onStart: () => (isLoading.value = "quantity" + idTransaction),
            onFinish: () => (isLoading.value = null),
            only: ["transactions", "box_stats", "total_to_pay", "balance"],
            preserveScroll: true
        }
    )
}

// Debounced update
const debounceUpdateQuantity = debounce(
    (routeUpdate: routeType, idTransaction: number, value: number, is_cut_view = false) => {
        onUpdateQuantity(routeUpdate, idTransaction, value, is_cut_view)
    },
    500
)

onBeforeUnmount(() => {
    debounceUpdateQuantity.cancel()
})

async function onSave() {
    const changedItems: Record<number, { newQty: number }> = {}
    const newProducts: Record<number, { quantity_ordered: number }> = {}

    rowsArray().forEach((row) => {
        // Handle new products
        if (typeof row.id === "string" && row.id.startsWith("new")) {
            const edited = createNewQty[row.id]
            const qty = edited
                ? Number(edited.quantity_ordered)
                : Number(row.quantity_ordered)

            newProducts[row.id_product] = {
                quantity_ordered: qty
            }
            return
        }

        // Handle existing products
        const clonedItem = createNewQty[row.id]
        if (clonedItem) {
            const newQty = Number(clonedItem.quantity_ordered)
            if (newQty !== Number(row.quantity_ordered)) {
                changedItems[row.id] = { newQty }
            }
        }
    })

    // nothing to save
    if (Object.keys(changedItems).length === 0 && Object.keys(newProducts).length === 0) {
        return
    }

    console.log("🟢 changedItems:", changedItems)
    console.log("🟡 newProducts:", newProducts)

    router.patch(
        route(props.modifyRoute.name, props.modifyRoute.parameters),
        {
            transactions: changedItems,
            products: newProducts
        },
        {
            onStart: () => (loadingsaveModify.value = true),
            onFinish: () => (loadingsaveModify.value = false),
            onSuccess: () => {
                // clear state
                Object.keys(createNewQty).forEach((k) => delete createNewQty[k])
                editingIds.value.clear()
                notify({
                    title: trans("Success"),
                    text: trans("Changes saved successfully"),
                    type: "success"
                })
            },
            onError: (e: any) => {
                notify({
                    title: trans("Something went wrong"),
                    text: e.message,
                    type: "error"
                })
            },
            preserveScroll: true
        }
    )
}


const openModal = (action: any) => {
    currentAction.value = action
    isModalProductListOpen.value = true
}

const addNewProduct = (products) => {
    const items = Array.isArray(products) ? products : [products]

    items.forEach((product) => {
        const existingIndex = props.data.data.findIndex(
            (p: any) => p.asset_code === product.code
        )

        const newItem = {
            id: existingIndex >= 0 ? props.data.data[existingIndex].id : "new-" + ulid(),
            asset_code: product.code,
            id_product: product.id,
            price: product.price,
            quantity_ordered: product.quantity_selected,
            net_amount: product.quantity_selected * product.price,
            asset_name: product.name,
            available_quantity: product.available_quantity
        }

        if (existingIndex >= 0) {
            // replace existing product
            props.data.data.splice(existingIndex, 1, newItem)
        } else {
            // add new
            props.data.data.push(newItem)
        }
    })

}

const onDeleteNewRow = (index) => {
    props.data.data.splice(index, 1)
}


defineExpose({
    openModal,
    onSave,
    rowsArray,
    createNewQty,
    loadingsaveModify

})

// Section: Discretionary discount
const selectedItemToEditNetAmount = ref(null)
const onCloseModalNetAmount = () => {
    isOpenModalEditNetAmount.value = false

    setTimeout(() => {
        selectedItemToEditNetAmount.value = null
    }, 300)
}
const isLoadingSubmitNetAmount = ref(false)
const isOpenModalEditNetAmount = ref(false)
const onSubmitEditNetAmount = () => {

    console.log("ccc", selectedItemToEditNetAmount.value)
    if (!selectedItemToEditNetAmount.value) {
        console.log("No item net amount selected")
        return
    }

    router.patch(
        route("grp.models.transaction.update_discretionary_discount", {
            transaction: selectedItemToEditNetAmount.value?.id
        }),
        {
            discretionary_offer: selectedItemToEditNetAmount.value?.discretionary_offer,
            discretionary_offer_label: selectedItemToEditNetAmount.value?.discretionary_offer_label
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingSubmitNetAmount.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully set discretionary discount percentage"),
                    type: "success"
                })
                onCloseModalNetAmount()
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: errors?.discretionary_offer || trans("Failed to set discretionary discount percentage. Try again"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmitNetAmount.value = false
            }
        }
    )
}

// Section: Cut View
const onSetCutView = async (proxyItem: {}, routeUpdate: routeType, newVal: boolean) => {
    router.patch(
        route(
            routeUpdate.name,
            routeUpdate.parameters
        ),
        {
            is_cut_view: newVal
        },
        {
            onStart: () => {
                set(proxyItem, 'is_transaction_loading', true)

            },
            onError: () => {
                console.log('eeerr', error)
                notify({
                    title: trans("Something went wrong"),
                    text: error.message || trans("Please try again or contact administrator"),
                    type: 'error'
                })
            },
            onFinish: () => {
                set(proxyItem, 'is_transaction_loading', false)
            }
        }
    )
}

const isOffersData = (offersData: any): boolean => {
    if (!offersData) return false
    const parsed = typeof offersData === 'string' ? JSON.parse(offersData) : offersData
    return Object.keys(parsed || {}).length > 0
}
</script>

<template>
    <div>
        <Table :resource="data" :name="tab" :rowColorFunction="(item) => {
            if (typeof item.id === 'string' && item.id.startsWith('new')) {
                return 'bg-yellow-50'
            }
            return ''
        }">


            <template #cell(image)="{ item }">
                <!-- <pre>{{ item }}</pre> -->
                <Image :src="item.image?.thumbnail" class="h-[50px] aspect-square" />
            </template>

            <!-- Column: Code -->
            <template #cell(asset_code)="{ item }">
                <Link v-if="productRoute(item)" :href="productRoute(item)" class="primaryLink">
                    {{ item.asset_code }}
                </Link>

                <div v-else>
                    {{ item.asset_code }}
                </div>
            </template>

            <!-- Column: Name / Stock -->
            <template #cell(asset_name)="{ item }">
                <div>
                    <div xclass="item.offers_data ? 'text-pink-600' : ''">{{ item.asset_name }}</div>
                    <div v-if="item.available_quantity !== undefined && item.available_quantity < 1">
                        <Tag label="Out of stock" no-hover-color :theme="7" size="xxs" />
                    </div>
                    <div v-else class="text-gray-500 italic text-xs">
                        Stock: {{ locale.number(item.available_quantity || 0) }} available
                    </div>

                    <Discount v-if="isOffersData(item.offers_data)" :offers_data="item.offers_data" />
                </div>
            </template>

            <!-- Column: Quantity Ordered -->
            <template #cell(quantity_ordered)="{ item, proxyItem }">
                <!-- <pre>{{ item.quantity_ordered_fractional }}</pre> -->
                <div v-if="layout.app.environment == 'local'" class="bg-yellow-400 w-fit">
                    {{ item.quantity_ordered_fractional }}
                </div> 
                <div class="flex items-center justify-end gap-2">
                    <div v-if="item.is_gift">
                        {{ locale.number(item.quantity_bonus) }}
                        <span v-tooltip="ctrans('Quantity of free gift')">
                            <FontAwesomeIcon icon="fal fa-gift" class="" fixed-width aria-hidden="true" />
                        </span>
                    </div>

                    <!-- Editable when creating and not in edit mode -->
                    <div v-else-if="(state === 'creating' || state === 'submitted') && !editingIds.has(item.id) && !is_shop_external"
                        class="w-fit flex gap-x-2">
                       <!--  <NumberWithButtonSave
                            :modelValue="Number(item.quantity_ordered)"
                            :routeSubmit="item.updateRoute"
                            isWithRefreshModel
                            keySubmit="quantity_ordered" :isLoading="isLoading === 'quantity' + item.id"
                            :readonly="readonly"
                            @update:modelValue="(e: number) => debounceUpdateQuantity(item.updateRoute, item.id, e)"
                            noUndoButton noSaveButton
                            :bindToTarget="{
                                min: 0,
                                max: item.available_quantity,
                            }"
                            :denominator="proxyItem.is_cut_view ? (Number(item.product_units) > 1 ? Number(item.product_units) : undefined) : undefined"
                        /> -->
                        <InputNumber 
                            :model-value="item.quantity_ordered_fractional[0]" 
                            @update:modelValue="(e: number) => debounceUpdateQuantity(item.updateRoute, item.id, e, proxyItem.is_cut_view)"
                            inputId="horizontal-buttons" 
                            showButtons 
                            buttonLayout="horizontal"
                            :step="1" 
                            min='0',
                            :max="proxyItem.is_cut_view ? (item.available_quantity * Number(item.quantity_ordered_fractional[1][1])) : item.available_quantity"
                            v-bind="bindToTarget" 
                            :suffix="proxyItem.is_cut_view && Number(item.quantity_ordered_fractional[1][1]) > 1
                                ? `/${Number(item.quantity_ordered_fractional[1][1])}`
                                : undefined
                                " 
                            :inputStyle="{
                                    width: bindToTarget?.fluid
                                        ? undefined
                                        : (proxyItem.is_cut_view && Number(item.quantity_ordered_fractional[1][1]) > 1 ? '75px' : '50px'),
                                    textAlign: 'center',
                                }" 
                            fluid
                            :key="proxyItem.is_cut_view + item.id"
                        >
                            <template #incrementbuttonicon>
                                <FontAwesomeIcon :icon="faPlus" />
                            </template>

                            <template #decrementbuttonicon>
                                  <FontAwesomeIcon :icon="faMinus" />
                            </template>
                        </InputNumber>

                        <!-- Toggle: is_cut_view -->
                        <span
                            v-if="layout.app.environment == 'local'"
                            @click="() => proxyItem.is_transaction_loading ? '' : onSetCutView(proxyItem, item.updateRoute, !proxyItem.is_cut_view)"
                            v-tooltip="trans('Cut view')"
                            class="text-lg align-middle opacity-60 cursor-pointer hover:opacity-100 flex items-center"
                            :class="proxyItem.is_cut_view ? 'text-orange-500' : ''"
                        >
                            <LoadingIcon v-if="proxyItem.is_transaction_loading" class="text-gray-700" />
                            <FontAwesomeIcon v-else icon="fas fa-fragile" class="" fixed-width aria-hidden="true" />
                        </span>
                    </div>

                    <!-- Read-only display -->
                    <div v-else-if="!editingIds.has(item.id)" class="flex flex-wrap items-center gap-x-2">
                        <span :class="[
                            (state === 'dispatched' && item.quantity_dispatched != item.quantity_ordered)
                            || ((state === 'packing' || state === 'packed') && item.quantity_picked != item.quantity_ordered)
                            || item.quantity_not_picked > 0
                                ? 'line-through'
                                : '',
                            item.quantity_not_picked > 0 ? 'text-red-500' : ''
                        ]"
                            v-tooltip="item.quantity_not_picked > 0 ? ctrans('Original quantity ordered') : ''"
                        >
                            {{ formatQuantity(item.quantity_ordered) }}
                        </span>
                        <span v-if="item.quantity_not_picked > 0" v-tooltip="ctrans('Quantity ordered (some is not picked)')">
                            {{ formatQuantity(item.quantity_ordered - item.quantity_not_picked) }}
                        </span>

                        <template v-if="(state === 'packing' || state === 'packed') && item.quantity_picked != item.quantity_ordered">
                            <span class="pl-3" :class="item.quantity_not_picked > 0 ? 'line-through text-red-500' : ''"
                                v-tooltip="item.quantity_not_picked > 0 ? ctrans('Original quantity to pick') : ''"
                            >
                                {{ formatQuantity(item.quantity_picked) }}
                            </span>
                            <span v-if="item.quantity_not_picked > 0" v-tooltip="item.quantity_not_picked > 0 ? ctrans('Quantity picked (some is not picked)') : ''">
                                {{ formatQuantity(item.quantity_picked - item.quantity_not_picked) }}
                            </span>
                        </template>

                        <span class="pl-3" v-if="state === 'dispatched'&&  item.quantity_dispatched!=item.quantity_ordered">
                            {{ formatQuantity(item.quantity_dispatched) }}
                            <!-- <FractionDisplay :fractionData="item.quantity_dispatched_fractional" /> -->
                        </span>

                    </div>

                    <!-- Inline edit mode with original quantity displayed -->
                    <div v-else class="items-center gap-2">
                        <span class="text-gray-500 italic text-sm">
                            original: {{ formatQuantity(item.quantity_ordered) }}
                        </span>
                        <NumberWithButtonSave v-model="createNewQty[item.id].quantity_ordered"
                                              :bindToTarget="{ min: 0 }" noUndoButton noSaveButton class="w-24" />
                    </div>
                </div>
            </template>

            <!-- Column: Batch Codes -->
            <template #cell(batch_codes)="{ item }">
                <div class="flex flex-wrap gap-1">
                    <span
                        v-for="code in (item.batch_codes ? item.batch_codes.split(', ') : [])"
                        :key="code"
                        class="text-xs px-1.5 py-0.5 rounded border border-blue-300 bg-blue-50 text-blue-700"
                    >
                        <FontAwesomeIcon icon="fal fa-barcode" class="mr-1" fixed-width aria-hidden="true" />
                        {{ code }}
                    </span>
                </div>
            </template>

            <!-- Section: Price -->
            <template #cell(price)="{ item }">
                <div v-if="item.is_gift">

                </div>
                <div v-else class="flex justify-end">
                    {{ locale.currencyFormat(item.currency_code || "", item.price) }}
                </div>
            </template>

            <!-- Section: Net Amount -->
            <template #cell(net_amount)="{ item }">
                <div v-if="item.is_gift">

                </div>
                <div v-else class="flex justify-end">
                    <div v-if="editingIds.has(item.id)" class="">
                        <!-- Original price tag -->
                        <div
                            class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium shadow-sm whitespace-nowrap my-2">
                            orig: {{ locale.currencyFormat(item.currency_code, item.net_amount) }}
                        </div>
                        <!-- Estimated price tag -->
                        <div
                            class="bg-yellow-100 text-yellow-800 px-4 py-1.5 rounded-full text-sm font-medium shadow-sm whitespace-nowrap inline-flex items-center justify-center">
                            est: {{ locale.currencyFormat(item.currency_code, (item.price *
                            createNewQty[item.id].quantity_ordered).toFixed(2)) }}
                        </div>
                    </div>
                    <div v-else>
                        <p class="" :class="item.gross_amount != item.net_amount ? 'text-green-500' : ''">
                            <span v-if="item.gross_amount != item.net_amount"
                                  class="text-gray-500 line-through mr-1 opacity-70">{{
                                    locale.currencyFormat(item.currency_code, item.gross_amount) }}</span>
                            <span>{{ locale.currencyFormat(item.currency_code || "", item.net_amount) }}</span>
                            <Button
                                v-if="!(['finalised', 'dispatched', 'cancelled'].includes(state)) && !is_shop_external && !item.is_gift"
                                @click="() => (selectedItemToEditNetAmount = item, isOpenModalEditNetAmount = true)"
                                v-tooltip="trans('Edit discretionary discount')" type="transparent" size="xs" key="1"
                                :icon="faMoneyCheckEditAlt" class="ml-1 !px-1 text-purple-400" />
                        </p>
                    </div>
                </div>
            </template>

            <!-- Column: Actions -->
            <template #cell(actions)="{ item }">
                <div class="flex gap-2 items-center">
                    <!-- Delete / Unselect -->
                    <Link v-if="state === 'creating'" :href="route(item.deleteRoute.name, item.deleteRoute.parameters)"
                          as="button" :method="item.deleteRoute.method" @start="() => (isLoading = 'unselect' + item.id)"
                          @finish="() => (isLoading = null)" v-tooltip="trans('Unselect this product')"
                          :preserveScroll="true">
                        <Button v-if="!readonly" icon="fal fa-times" type="negative" size="xs"
                                :loading="isLoading === 'unselect' + item.id" />
                    </Link>

                    <!-- Edit / Cancel -->
                    <div v-if="state !== 'creating'" class="flex gap-2 items-center">
                        <button v-if="!editingIds.has(item.id) && layout?.app?.environment === 'local'"
                                class="h-9 align-bottom text-center" @click="startEdit(item)"
                                aria-label="Edit Product Order" v-tooltip="'Edit Product Order'">
                            <FontAwesomeIcon :icon="faPencil" class="h-5 text-gray-500 hover:text-gray-700"
                                             aria-hidden="true" />
                        </button>

                        <Button v-else-if="editingIds.has(item.id)" type="negative" v-tooltip="'Cancel edit'"
                                :icon="faTimes" @click="onCancel(item)" size="sm" aria-label="Cancel edit" />

                        <Button v-if="typeof item.id === 'string' && item.id.startsWith('new')" type="negative"
                                v-tooltip="'delete'" :icon="faTrashAlt" @click="() => onDeleteNewRow(item.rowIndex)"
                                size="sm" />
                    </div>
                </div>
            </template>

        </Table>

        <!-- Section: Modal edit discretionary discount -->
        <Modal v-if="!(['finalised', 'dispatched', 'cancelled'].includes(state)) && !is_shop_external" :isOpen="isOpenModalEditNetAmount" @onClose="() => onCloseModalNetAmount()" width="w-full max-w-lg">
            <div class="text-center mb-4">
                <div class="font-semibold text-2xl">Update for {{ selectedItemToEditNetAmount?.asset_code }}:</div>
                <div class="opacity-80 italic text-sm">
                    {{ selectedItemToEditNetAmount?.asset_name }}
                </div>
            </div>

            <div class="flex flex-col items-center gap-4">
                <!-- Input: Percentage -->
                <div class="w-full ">
                    <label class="block text-sm font-medium mb-2">
                        {{ trans("Discretionary discount percentage") }}:
                    </label>
                    <InputNumber
                        :modelValue="get(selectedItemToEditNetAmount, 'discretionary_offer', 0)"
                        @input="(e) => set(selectedItemToEditNetAmount, 'discretionary_offer', e?.value)"
                        :max-fraction-digits="2"
                        suffix="%"
                        :disabled="isLoadingSubmitNetAmount"
                    />
                </div>

                <!-- Input: Label -->
                <div class="w-full ">
                    <label class="block text-sm font-medium mb-2">
                        {{ trans("Discretionary discount Label") }}:
                    </label>
                    <InputText
                        :modelValue="get(selectedItemToEditNetAmount, 'discretionary_offer_label', '')"
                        @input="(e) => (set(selectedItemToEditNetAmount, 'discretionary_offer_label', e?.target?.value))"
                        :placeholder="ctrans('Discretionary Discount')"
                        :disabled="isLoadingSubmitNetAmount"
                    />
                </div>

                <!-- Section: preview -->
                <div class="w-full border-y py-4 flex justify-center">
                    <BasicDiscount
                        :offers_data="{
                            v: get(selectedItemToEditNetAmount, 'discretionary_offer', 0),
                            o: {
                                oc: 0,
                                o: 0,
                                oa: 0,
                                t: 'percentage',
                                p: String(parseFloat((Number(selectedItemToEditNetAmount?.discretionary_offer || 0)).toFixed(2))) + '%',
                                l: get(selectedItemToEditNetAmount, 'discretionary_offer_label', '') || ctrans('Discretionary Discount'),
                                st: null,
                                sto: null
                            }
                        }"
                    />
                </div>

                <div class="w-full flex gap-4 mt-4">
                    <Button type="negative" size="md" :disabled="isLoadingSubmitNetAmount" icon="far fa-arrow-left"
                            @click="onCloseModalNetAmount" :label="trans('Cancel')">
                    </Button>

                    <Button type="primary" size="md" :loading="isLoadingSubmitNetAmount" icon="fad fa-save"
                            @click="onSubmitEditNetAmount" full label="Save">
                    </Button>
                </div>
            </div>
        </Modal>

        <Modal :isOpen="isModalProductListOpen" @onClose="isModalProductListOpen = false" width="w-full max-w-6xl">
            <ProductsSelectorAutoSelect
                :headLabel="trans('Add products to Order') + ' #' + (Array.isArray(props.data) ? '' : props.data?.reference)"
                :routeFetch="props.routesProductsListModification" :isLoadingSubmit="false" :listLoadingProducts="false"
                withQuantity @submit="addNewProduct" />
        </Modal>
    </div>
</template>
