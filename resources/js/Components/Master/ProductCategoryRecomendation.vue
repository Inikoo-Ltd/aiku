<script setup lang="ts">
import { ref } from "vue"
import FamilySetOrderingPositionOfProduct from "./FamilySetOrderingPositionOfProduct.vue";
import { trans } from "laravel-vue-i18n";
import Button from "@/Components/Elements/Buttons/Button.vue"
import ListSelector from "@/Components/Selector.vue"

const props = defineProps<{
    data: any
}>()

// ✅ shape sesuai komponen ordering
const listProducts = ref({
    data: props.data?.data ?? []
})

const productDialog = ref()

const openAddProduct = () => {
    productDialog.value?.open()
}


</script>

<template>

    
    <FamilySetOrderingPositionOfProduct :data="listProducts">

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

    <!-- ✅ selector langsung bind ke data -->
    <ListSelector
        ref="productDialog"
        v-model="listProducts.data"
        :routeFetch="{
            name: 'grp.masters.master_shops.show.master_products.index',
            parameters: { masterShop: route().params['masterShop'] }
        }"
    />
</template>