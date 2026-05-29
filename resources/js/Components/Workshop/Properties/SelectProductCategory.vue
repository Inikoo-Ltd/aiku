<script setup lang="ts">
import { computed, ref } from 'vue'
import draggable from 'vuedraggable'

import Button from '@/Components/Elements/Buttons/Button.vue'
import SelectorProductCategory from '@/Components/SelectorProductCategory.vue'
import { faTrashAlt } from '@far'

interface Product {
    id: number
    code?: string
    name?: string
    slug?: string
    image_url?: string
    formatted_price?: string
}

const props = defineProps<{
    modelValue: Product[]
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: Product[]): void
}>()

const productDialog = ref()

const products = computed({
    get: () => props.modelValue || [],
    set: (value) => emit('update:modelValue', value)
})

const openAddProduct = () => {
    productDialog.value?.open()
}

const removeProduct = (index: number) => {
    const items = [...products.value]
    items.splice(index, 1)
    products.value = items
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <label class="text-sm font-medium text-gray-700">
            </label>

            <Button label="+ Add Product" type="tertiary" size="xs" @click="openAddProduct" />
        </div>

        <div v-if="!products?.length"
            class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
            {{ ctrans('No products selected') }}
        </div>

        <draggable v-else v-model="products" item-key="id" handle=".drag-handle" class="space-y-3">
            <template #item="{ element, index }">
                <div class="flex items-center gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="drag-handle cursor-move text-gray-400 hover:text-gray-600">
                        ☰
                    </div>

                    <img v-if="element.image_url" :src="element.image_url" :alt="element.name"
                        class="h-16 w-16 rounded object-cover">

                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-gray-900">
                            {{ element.name }}
                        </div>

                        <div v-if="element.code" class="mt-1 text-xs text-gray-500">
                            {{ element.code }}
                        </div>

                        <div v-if="element.formatted_price" class="mt-1 text-sm text-gray-700">
                            {{ element.formatted_price }}
                        </div>
                    </div>

                    <div class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-600">
                        #{{ index + 1 }}
                    </div>

                    <Button :icon=faTrashAlt type="negative" size="xs" @click="removeProduct(index)" />
                </div>
            </template>
        </draggable>

        <SelectorProductCategory ref="productDialog" v-model="products" :routeFetchDepartment="{
            name: 'grp.org.shops.show.catalogue.departments.index',
            parameters: {
                shop: route().params.shop,
                organisation: route().params.organisation
            }
        }" :routeFetchSubDepartment="{
                name: 'grp.org.shops.show.catalogue.sub_departments.index',
                parameters: {
                    shop: route().params.shop,
                    organisation: route().params.organisation
                }
            }" :routeFetchFamily="{
                name: 'grp.org.shops.show.catalogue.families.index',
                parameters: {
                    shop: route().params.shop,
                    organisation: route().params.organisation
                }
            }" />
    </div>
</template>