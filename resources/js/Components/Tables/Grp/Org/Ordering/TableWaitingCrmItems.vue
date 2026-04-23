<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 13 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStickyNote, faExchangeAlt, faSearch, faSave, faTimes, faTruck, faShoppingCart } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { computed, inject, reactive, ref, watch } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Modal from "@/Components/Utils/Modal.vue"
import axios from "axios"
import { debounce } from "lodash-es"
import { InputNumber } from "primevue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
library.add(faStickyNote, faExchangeAlt, faSearch, faSave, faTimes, faTruck, faShoppingCart)

defineProps<{
    data: TableTS
    tab?: string
}>()

const layout = inject('layout', layoutStructure)
const locale = inject('locale', aikuLocaleStructure)

const orderRoute = (item: Record<string, any>): string | null => {
    if (!item['order_slug'] || !item['shop_slug'] || !item['organisation_slug']) {
        return null
    }
    try {
        return route('grp.org.shops.show.ordering.orders.show', [
            item['organisation_slug'],
            item['shop_slug'],
            item['order_slug'],
        ])
    } catch {
        return null
    }
}

const setAsNotPickRoute = (item: Record<string, any>): string | null => {
    if (!item['id']) {
        return null
    }

    try {
        return route('grp.models.delivery_note_item.not_picking_from_waiting_crm.store', {
            deliveryNoteItem: item['id']
        })
    } catch {
        return null
    }
}

const replaceProductRoute = (item: Record<string, any>): string | null => {
    if (!item['id']) {
        return null
    }

    try {
        return route('grp.models.delivery_note_item.waiting_items_replace_product', [
            item['id'],
        ])
    } catch {
        return null
    }
}

// Section: Modal Replace Product
const isOpenModalReplaceProduct = ref(false)
const selectedItem = ref<Record<string, any> | null>(null)
const modalProducts = ref<any[]>([])
const modalSearchQuery = ref('')
const isModalProductsLoading = ref(false)
const productQuantities = reactive<Record<number, { quantity: number; code: string; name: string; stock: number }>>({})
const isSubmittingReplaceProduct = ref(false)

const openReplaceProductModal = (item: Record<string, any>) => {
    selectedItem.value = item
    modalSearchQuery.value = ''
    Object.keys(productQuantities).forEach(key => delete productQuantities[Number(key)])
    isOpenModalReplaceProduct.value = true
    fetchModalProducts()
}

const closeReplaceProductModal = () => {
    isOpenModalReplaceProduct.value = false
    // selectedItem.value = null
}

const fetchModalProducts = debounce(async () => {
    if (!selectedItem.value) return
    isModalProductsLoading.value = true
    try {
        const params: Record<string, any> = { shop: selectedItem.value.shop_slug }
        if (modalSearchQuery.value.trim()) {
            params['filter[global]'] = modalSearchQuery.value.trim()
        }
        const url = route('grp.json.shop.products', params)
        const response = await axios.get(url)
        const products = response.data.data ?? []
        products.forEach((product: any) => {
            if (!(product.id in productQuantities)) {
                productQuantities[product.id] = { quantity: 0, code: product.code, name: product.name, stock: product.stock ?? 0 }
            }
        })
        modalProducts.value = products
    } catch (error) {
        console.error('Error fetching products:', error)
    } finally {
        isModalProductsLoading.value = false
    }
}, 300)

watch(modalSearchQuery, () => {
    fetchModalProducts()
})


const selectedProducts = computed(() =>
    Object.entries(productQuantities)
        .filter(([, p]) => p.quantity > 0)
        .map(([id, p]) => ({ id: Number(id), code: p.code, name: p.name, quantity: p.quantity }))
)

const unselectProduct = (id: number) => {
    if (productQuantities[id]) {
        productQuantities[id].quantity = 0
    }
}

interface SuccessContext {
    replacedItem: Record<string, any>
    newProducts: { id: number; code: string; name: string; quantity: number }[]
}

const isModalConfirmationSuccess = ref(false)
const successContext = ref<SuccessContext | null>(null)

const submitReplaceProduct = () => {
    if (!selectedItem.value) return
    if (selectedProducts.value.length === 0) return
    const submitRoute = replaceProductRoute(selectedItem.value)
    if (!submitRoute) return
    isSubmittingReplaceProduct.value = true

    const snapshotSelectedProducts = [...selectedProducts.value]

    router.post(submitRoute, { products: selectedProducts.value.map(({ id, quantity }) => ({ id, quantity })) }, {
        preserveScroll: true,
        onSuccess: () => {
            isOpenModalReplaceProduct.value = false
            successContext.value = {
                replacedItem: { ...selectedItem.value },
                newProducts: snapshotSelectedProducts,
            }
            notify({
                title: ctrans("Success!"),
                text: ctrans('Items :itemOld has been replaced', { itemOld: selectedItem.value?.org_stock_code ?? ''}),
                type: "success",
            })
            isModalConfirmationSuccess.value = true
        },
        onFinish: () => { isSubmittingReplaceProduct.value = false },
    })
}
</script>

<template>
    <Table :resource="data" :name="tab" rowAlignTop>
        <template #cell(delivery_note_reference)="{ item }">
            <div class="flex gap-2 flex-wrap items-center">
                <FontAwesomeIcon icon="fal fa-truck" class="opacity-60" fixed-width aria-hidden="true" />
                <span class="font-semibold">{{ item.delivery_note_reference }}</span>
                <FontAwesomeIcon
                    v-if="item.delivery_note_is_premium_dispatch"
                    v-tooltip="ctrans('Priority dispatch')"
                    icon="fas fa-star"
                    class="text-yellow-500"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-if="item.order_reference" class="mt-1 text-xs text-gray-500">
                <Link v-if="orderRoute(item)" :href="orderRoute(item)!" class="primaryLink">
                    <FontAwesomeIcon icon="fal fa-shopping-cart" class="opacity-75 mr-1" fixed-width aria-hidden="true" />
                    {{ item.order_reference }}
                </Link>
                <span v-else>{{ item.order_reference }}</span>
            </div>
        </template>

        <template #cell(items)="{ item: deliveryNoteRow }">
            <div v-if="deliveryNoteRow.items?.length" class="divide-y divide-gray-100">
                <div
                    v-for="subItem in deliveryNoteRow.items"
                    :key="subItem.id"
                    class="py-3 first:pt-1 flex flex-wrap items-start gap-x-6 gap-y-2"
                >
                    <!-- Item info -->
                    <div class="flex-1 min-w-0 flex flex-col gap-0.5">
                        <div>
                            <span class="font-semibold">{{ subItem.org_stock_code }}</span>
                            <span class="ml-1.5 text-gray-600 italic opacity-80">{{ subItem.org_stock_name }}</span>
                        </div>
                        <div class="tabular-nums text-sm text-gray-500">
                            {{ Number(subItem.quantity_waiting_crm) }} {{ ctrans("items") }}
                        </div>
                        <div v-if="subItem.notes" class="text-left border border-gray-300 bg-gray-100 px-2 py-1 rounded text-xs w-fit">
                            <FontAwesomeIcon icon="fal fa-sticky-note" fixed-width aria-hidden="true" />
                            {{ subItem.notes }}
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 shrink-0 flex-wrap">
                        <ButtonWithLink
                            v-tooltip="ctrans(':itemNotPick items will not picked, and will not billed to customer', { itemNotPick: Number(subItem.quantity_waiting_crm) })"
                            :url="setAsNotPickRoute(subItem)"
                            method="post"
                            :label="ctrans(`Don't pick :itemNotPick items`, { itemNotPick: Number(subItem.quantity_waiting_crm) })"
                            type="negative"
                            icon="fas fa-skull"
                            size="xs"
                        />

                        <!-- Refresh Faire Data -->
                        <ButtonWithLink
                            v-if="deliveryNoteRow.shop_type == 'external' && deliveryNoteRow.shop_engine === 'faire'"
                            :label="ctrans('Refresh Faire data')"
                            size="xs"
                            type="tertiary"
                            icon="fal fa-sync-alt"
                            :routeTarget="{
                                name: 'grp.models.order.update_faire',
                                parameters: { order: deliveryNoteRow.order_id },
                                method: 'post'
                            }"
                        />

                        <!-- Replace Product -->
                        <Button
                            v-else-if="replaceProductRoute(subItem)"
                            :label="ctrans('Replace :itemNotPick items', { itemNotPick: Number(subItem.quantity_waiting_crm) })"
                            size="xs"
                            type="positive"
                            icon="fal fa-exchange-alt"
                            @click="openReplaceProductModal({
                                ...subItem,
                                shop_slug: subItem.shop_slug ?? deliveryNoteRow.shop_slug,
                                shop_type: deliveryNoteRow.shop_type,
                                shop_engine: deliveryNoteRow.shop_engine,
                                order_id: deliveryNoteRow.order_id,
                                order_slug: deliveryNoteRow.order_slug,
                                order_reference: deliveryNoteRow.order_reference,
                                organisation_slug: deliveryNoteRow.organisation_slug,
                            })"
                        />
                    </div>
                </div>
            </div>
            <div v-else class="text-gray-400 text-sm italic py-2">
                {{ ctrans('No items') }}
            </div>
        </template>
    </Table>

    <!-- Modal: Replace product -->
    <Modal :isOpen="isOpenModalReplaceProduct" width="w-full max-w-3xl" @onClose="closeReplaceProductModal" :closeButton="true">
        <div class="flex flex-col gap-4">
            <h2 class="text-xl font-semibold text-center">{{ ctrans('Replace Product') }}</h2>

            <!-- Section: product to replace -->
            <div class="">
                <div>
                    {{ ctrans("Product to replace") }}:
                </div>
                <div class="bg-amber-50 rounded px-4 py-2 text-sm text-amber-700 border border-amber-400">
                    <div class="flex justify-between items-start gap-4">
                        <div>
                            <span class="font-semibold">{{ selectedItem?.org_stock_code }}</span>
                            <span class="ml-1.5 opacity-80">{{ selectedItem?.org_stock_name }}</span>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="tabular-nums font-semibold">
                                {{ Number(selectedItem?.quantity_waiting_crm) }} {{ ctrans("items") }}
                            </div>
                            <div v-if="selectedItem?.net_amount" class="tabular-nums text-xs opacity-70 mt-0.5">
                                {{ locale.currencyFormat(selectedItem?.currency_code, selectedItem?.net_amount) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: search product -->
            <div>
                <div>
                    {{ ctrans("Select product") }}:
                </div>
                <div class="relative">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fixed-width />
                    <input
                        v-model="modalSearchQuery"
                        type="text"
                        :placeholder="ctrans('Search products...')"
                        class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <div v-if="isModalProductsLoading" class="h-96 flex justify-center py-10 text-gray-400 text-3xl">
                <LoadingIcon />
            </div>

            <!-- Section: list products -->
            <div v-else class="overflow-y-auto h-96 border border-gray-200 rounded-lg isolate">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 border-b border-gray-200 sticky top-0 z-10">
                        <tr>
                            <th class="text-left px-4 py-2 font-medium">{{ ctrans('Name') }}</th>
                            <th class="text-right px-4 py-2 font-medium">{{ ctrans('Available') }}</th>
                            <th class="text-right px-4 py-2 font-medium">{{ ctrans('Quantity') }}</th>
                        </tr>
                    </thead>
                    
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="modalProducts.length === 0">
                            <td colspan="4" class="text-center py-10 text-gray-400">{{ ctrans('No products found') }}</td>
                        </tr>
                        <tr
                            v-for="product in modalProducts"
                            :key="product.id"
                            :class="productQuantities[product.id]?.quantity > 0 ? 'bg-green-100'
                            : product.stock > 0
                                ? ''
                                : 'bg-gray-100 opacity-60'
                            "
                            class="transition-colors"
                        >
                            <td class="px-4 py-3 text-gray-700">
                                <div class="font-bold">{{ product.code }}</div>
                                <div class="italic opacity-75">{{ product.name }}</div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums whitespace-nowrap" :class="!product.stock ? 'text-red-500' : 'text-gray-600'">
                                {{ product.stock > 0 ? locale.number(product.stock) : ctrans('Empty stock') }}
                            </td>
                            <td class="px-4 py-3 flex justify-end">
                                <InputNumber
                                    :modelValue="productQuantities[product.id]?.quantity ?? 0"
                                    @update:model-value="(e) => { if (productQuantities[product.id]) productQuantities[product.id].quantity = e ?? 0 }"
                                    @input="(e) => { if (productQuantities[product.id]) productQuantities[product.id].quantity = Number(e.value) || 0 }"
                                    :min="0"
                                    :max="product.stock ?? 0"
                                    :disabled="!product.stock"
                                    inputClass="w-28"
                                    showButtons
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-2 pt-2 border-t border-gray-200">
                <!-- Section: selected products list -->
                <div class="flex flex-wrap gap-1.5">
                    <template v-if="selectedProducts.length > 0">
                        <div
                            v-for="product in selectedProducts"
                            :key="product.id"
                            class="inline-flex items-center gap-1.5 bg-green-100 border border-green-300 text-green-800 rounded-full px-3 py-1 text-xs font-medium"
                        >
                            <span class="font-bold">{{ product.code }}</span>
                            <span class="opacity-70">×{{ product.quantity }}</span>
                            <button
                                type="button"
                                @click="unselectProduct(product.id)"
                                class="ml-0.5 text-green-600 hover:text-red-600 transition-colors"
                                :aria-label="ctrans('Remove')"
                            >
                                <FontAwesomeIcon icon="fal fa-times" class="text-xs" />
                            </button>
                        </div>
                    </template>
                    <div v-else class="border border-transparent">
                        {{ ctrans("No selected products") }}
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-400">
                        {{ selectedProducts.length }} {{ ctrans('product(s) selected') }}
                    </span>
                    <div class="flex gap-2">
                        <Button :label="ctrans('Cancel')" type="negative" @click="closeReplaceProductModal" />
                        <Button
                            :label="ctrans('Save')"
                            icon="fad fa-save"
                            :loading="isSubmittingReplaceProduct"
                            :disabled="selectedProducts.length === 0"
                            @click="submitReplaceProduct"
                        />
                    </div>
                </div>
            </div>
        </div>
    </Modal>

    <!-- Modal: information after success -->
    <Modal
        :isOpen="isModalConfirmationSuccess"
        @onClose="isModalConfirmationSuccess = false"
        width="w-full max-w-xl"
        :closeButton="true">
        <div v-if="successContext" class="flex flex-col gap-3 py-2">
            <div class="flex flex-col items-center gap-2 text-center mb-8">
                <FontAwesomeIcon icon="fas fa-check-circle" class="text-green-500 text-4xl" fixed-width aria-hidden="true" />
                <h3 class="font-semibold text-xl xtext-gray-800">{{ ctrans('Product replaced successfully') }}</h3>
            </div>

            <div class="flex flex-col gap-1 border-b border-gray-300 pb-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans('Order') }}</div>
                <a
                    v-if="orderRoute(successContext.replacedItem)"
                    :href="orderRoute(successContext.replacedItem)!"
                    target="_blank"
                    class="font-semibold text-base flex justify-between items-center"
                    xclick="isModalConfirmationSuccess = false"
                >
                    <div>
                        <FontAwesomeIcon icon="fal fa-shopping-cart" class="opacity-75 mr-1" fixed-width aria-hidden="true" />
                        #{{ successContext.replacedItem.order_reference }}
                    </div>
                    <div class="underline font-normal opacity-70 italic text-xs hover:opacity-100">
                        {{ ctrans("Click to open") }} ->
                    </div>
                </a>
                <span v-else class="font-semibold text-base">{{ successContext.replacedItem.order_reference ?? '-' }}</span>
            </div>

            <!-- Section: Replaced item -->
            <div class="flex flex-col gap-1">
                <div class="text-xs xfont-semibold xuppercase tracking-wide text-gray-400">{{ ctrans('Replaced items') }}</div>
                <div class="flex justify-between items-center bg-red-100 border border-red-200 rounded-lg px-4 py-3 text-sm">
                    <div>
                        <span class="font-bold text-gray-700">{{ successContext.replacedItem.org_stock_code }}</span>
                        <span class="block text-gray-500 italic">{{ successContext.replacedItem.org_stock_name }}</span>
                    </div>
                    <div class="text-right tabular-nums text-gray-500 shrink-0">
                        <div>{{ Number(successContext.replacedItem.quantity_waiting_crm) }} {{ ctrans('items') }}</div>
                        <div v-if="successContext.replacedItem.net_amount" class="text-xs opacity-70 mt-0.5">
                            {{ locale.currencyFormat(successContext.replacedItem.currency_code, successContext.replacedItem.net_amount) }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <div class="text-xs x tracking-wide text-gray-400">{{ ctrans('New products') }}</div>
                <div class="flex flex-col gap-1.5">
                    <div
                        v-for="product in successContext.newProducts"
                        :key="product.id"
                        class="flex justify-between items-center bg-green-100 border border-green-200 rounded-lg px-4 py-3 text-sm gap-x-4"
                    >
                        <div>
                            <span class="font-bold text-gray-700">{{ product.code }}</span>
                            <span class="block xml-2 text-gray-500 italic">{{ product.name }}</span>
                        </div>
                        <div class="tabular-nums text-gray-500 text-right">{{ product.quantity }} {{ ctrans('items') }}</div>
                    </div>
                </div>
            </div>

            <Button :label="ctrans('Done')" type="tertiary" full @click="isModalConfirmationSuccess = false" />
        </div>
    </Modal>
</template>
