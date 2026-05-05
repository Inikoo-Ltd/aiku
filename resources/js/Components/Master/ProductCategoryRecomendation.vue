<script setup lang="ts">
import { ref } from "vue"
import FamilySetOrderingPositionOfProduct from "./FamilySetOrderingPositionOfProduct.vue";
import { trans } from "laravel-vue-i18n";
import Button from "@/Components/Elements/Buttons/Button.vue"
import ListSelector from "@/Components/Selector.vue"
import { router } from "@inertiajs/vue3";
import { notify } from "@kyvg/vue3-notification";
import axios from "axios";

const props = defineProps<{
    data: any
    product_category_id?: number
}>()
console.log('Props:', props)
// ✅ shape sesuai komponen ordering
const listProducts = ref({
    data: props.data?.data ?? []
})

const productDialog = ref()

const openAddProduct = () => {
    productDialog.value?.open()
}

const loadingOrder = ref(false)


const SaveOrder = async () => {
    loadingOrder.value = true

    try {
        await axios.patch(
            route('grp.models.master_product_category.related_assets.sync', {
                masterProductCategory: props.product_category_id
            }),
            {
                master_asset_ids: listProducts.value.data.map((product: any, index: number) => ({
                    id: product.id,
                    code: product.code,
                    position: product.index_under_family ?? index,
                }))
            }
        )

        notify({
            title: trans("Success!"),
            text: trans("Successfully reordered the products"),
            type: "success"
        })

    } catch (error: any) {
        console.error(error)

        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message || trans("Failed to reorder products"),
            type: "error"
        })

    } finally {
        loadingOrder.value = false
    }
}


</script>

<template>
    <div class="p-4 space-y-4">

        <!-- HEADER ACTION -->
        <div class="flex justify-between items-center">
            <div class="text-xl font-semibold text-gray-700">
                {{ trans('Product Recommendations Ordering') }}
            </div>

            <Button 
                label="Save Order" 
                @click="SaveOrder" 
                :disabled="loadingOrder" 
                type="save"
            />
        </div>

        <!-- MAIN CONTENT -->
        <div class="bg-white border rounded-lg p-4">

            <FamilySetOrderingPositionOfProduct 
                :data="listProducts"
                @update:data="handleUpdateData"
            >

                <!-- TOP ACTION -->
                <template #before-button-list>
                    <div class="flex justify-end mx-3">
                        <Button 
                            label=" + Add Product" 
                            type="tertiary" 
                            size="xs"
                            @click="openAddProduct"
                        />
                    </div>
                </template>

                <!-- EMPTY STATE -->
                <template #empty>
                    <div class="flex flex-col items-center justify-center text-center py-12 px-6 border border-dashed rounded-lg bg-gray-50">

                        <div class="text-sm font-semibold text-gray-700">
                            {{ trans('No products found') }}
                        </div>

                        <div class="text-xs text-gray-500 mt-1 max-w-xs">
                            {{ trans('Start by adding your first product to this list.') }}
                        </div>

                        <Button 
                            class="mt-5" 
                            label="Add Product" 
                            type="create" 
                            @click="openAddProduct" 
                        />
                    </div>
                </template>

            </FamilySetOrderingPositionOfProduct>

        </div>

        <!-- SELECTOR (DIALOG CONTROLLER) -->
        <ListSelector
            ref="productDialog"
            v-model="listProducts.data"
            :routeFetch="{
                name: 'grp.masters.master_shops.show.master_products.index',
                parameters: { masterShop: route().params['masterShop'] }
            }"
        />

    </div>
</template>