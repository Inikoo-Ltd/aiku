<script setup lang="ts">
import { inject, ref } from "vue"
import axios from "axios"
import SetOrderingPositionOfProduct from "@/Components/Master/SetOrderingPositionOfProduct.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { Message } from "primevue"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { faGlobe, faSparkles } from "@fal"

const props = defineProps<{
    data: {
        products: any[]
        open_shops_count: number
        currency_code: string
        editable: boolean
    }
    product_category_id?: number
}>()

const locale = inject("locale", aikuLocaleStructure)

const products = ref<any[]>(props.data?.products ?? [])
const isDirty = ref(false)
const isSaving = ref(false)
const isSuggesting = ref(false)
const errorMessage = ref<string | null>(null)
const isSaved = ref(false)

const sortOptions = [
    { key: "sales_12m", label: ctrans("Sales 12m"), type: "number" as const, defaultDirection: "desc" as const },
    { key: "shops_in_stock", label: ctrans("In stock"), type: "number" as const, defaultDirection: "desc" as const },
    { key: "code", label: ctrans("Code"), type: "string" as const },
]

const onReorder = (reordered: any[]) => {
    products.value = reordered
    isDirty.value = true
    isSaved.value = false
}

const suggestOrder = async () => {
    errorMessage.value = null
    isSuggesting.value = true
    try {
        const { data } = await axios.get(route("grp.json.master_product_category.suggest_index_order", { masterProductCategory: props.product_category_id }))
        const position = new Map<string, number>(data.codes.map((code: string, index: number) => [code, index]))
        onReorder([...products.value].sort((a, b) => (position.get(a.code) ?? Infinity) - (position.get(b.code) ?? Infinity)))
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message || ctrans("The AI suggestion is not available right now, try again later")
    } finally {
        isSuggesting.value = false
    }
}

const saveOrder = async () => {
    errorMessage.value = null
    isSaving.value = true
    try {
        await axios.patch(
            route("grp.models.master_product_category.reorder_index", { masterProductCategory: props.product_category_id }),
            {
                products: products.value.map((product: any, index: number) => ({
                    id: product.id,
                    code: product.code,
                    index_under_master_family: index,
                })),
            }
        )
        isDirty.value = false
        isSaved.value = true
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message || ctrans("Failed to publish the order")
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div class="p-4 space-y-4">
        <div class="flex justify-between items-center gap-4">
            <div class="text-xl font-semibold text-gray-700">
                {{ ctrans("Index ordering") }}
                <span class="ml-2 text-sm font-normal text-gray-500">{{ ctrans("Order of the products on the family page in every shop") }}</span>
            </div>
            <div class="flex items-center gap-3">
                <span v-if="isSaved" class="text-sm text-green-600">{{ ctrans("Published") }}</span>
                <Button v-if="data?.editable" type="save" :icon="faGlobe" :label="ctrans('Publish')" :disabled="!isDirty" :loading="isSaving" @click="saveOrder" />
            </div>
        </div>

        <Message severity="secondary" size="small">
            {{ ctrans("Sorting by sales or stock, or AI order, only rearranges this list once, with today's numbers. Publish puts the order on the family page of every shop that follows the master order: logged-in customers see it within seconds, visitors who are not logged in within about 10 minutes. It then stays fixed until someone publishes again, and does not change by itself when sales or stock change.") }}
        </Message>

        <Message v-if="errorMessage" severity="error" size="small">{{ errorMessage }}</Message>

        <div class="bg-white border rounded-lg p-4">
            <SetOrderingPositionOfProduct
                :data="products"
                :disabled="!data?.editable"
                :sort-options="sortOptions"
                :paste-lookup-route="{ name: 'grp.json.master_product_category.products_by_codes', parameters: { masterProductCategory: product_category_id } }"
                @update:data="onReorder"
            >
                <template #after-sort-options>
                    <Button
                        v-if="data?.editable"
                        type="tertiary"
                        size="xs"
                        :icon="faSparkles"
                        :label="ctrans('AI order')"
                        v-tooltip="ctrans('Suggests an order from the sales and stock of every shop. Check it, then press Publish.')"
                        :loading="isSuggesting"
                        @click="suggestOrder"
                    />
                </template>
                <template #list-extra="{ item }">
                    <div class="flex items-center gap-6 text-xs text-gray-500 tabular-nums whitespace-nowrap">
                        <div class="w-28 text-right" v-tooltip="ctrans('Group sales, last 12 months, all shops')">
                            <div class="font-medium text-gray-700">{{ locale.currencyFormat(data.currency_code, item.sales_12m ?? 0) }}</div>
                            <div>{{ ctrans(":count sold", { count: Number(item.sold_12m ?? 0).toLocaleString() }) }}</div>
                        </div>
                        <div class="w-28 text-right" v-tooltip="ctrans('Open shops selling it, and how many of them have stock')">
                            <div class="font-medium text-gray-700">{{ ctrans(":count/:total shops", { count: String(item.shops_selling ?? 0), total: String(data.open_shops_count) }) }}</div>
                            <div :class="(item.shops_in_stock ?? 0) < (item.shops_selling ?? 0) ? 'text-amber-600' : ''">{{ ctrans(":count in stock", { count: String(item.shops_in_stock ?? 0) }) }}</div>
                        </div>
                    </div>
                </template>
            </SetOrderingPositionOfProduct>
        </div>
    </div>
</template>
