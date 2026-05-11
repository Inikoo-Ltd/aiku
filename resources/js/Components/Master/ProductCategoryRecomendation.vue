<script setup lang="ts">
import { ref } from "vue"
import SetOrderingPositionOfProduct from "@/Components/Master/SetOrderingPositionOfProduct.vue";
import { trans } from "laravel-vue-i18n";
import Button from "@/Components/Elements/Buttons/Button.vue"
import ListSelector from "@/Components/Selector.vue"
import { notify } from "@kyvg/vue3-notification";
import axios from "axios";
import Image from "../Image.vue";

const props = defineProps<{
    data: {
        data: any,
        editable: boolean
        route_sync_related_products?: {
            name: string,
            parameters: Record<string, any>
        }
        sync_payload_key?: string
        route_get_products : {
            name: string,
            parameters: Record<string, any>
        }
    }
    product_category_id?: number
}>()

const listProducts = ref({
    data: props.data?.data ?? []
})
const saveActive = ref(false)
const productDialog = ref()

const loadingOrder = ref(false)

const openAddProduct = () => {
    if (!props.data?.editable) return

    productDialog.value?.open()
}

const SaveOrder = async () => {
    if (!props.data?.editable) return
    if (!props.data?.route_sync_related_products?.name) return

    loadingOrder.value = true

    console.log(
        'Saving order with the following products:',
        listProducts.value.data
    )

    const payloadKey = props.data?.sync_payload_key || 'master_asset_ids'
    const payloadValues = listProducts.value.data.data.map((product: any) => product.id)
    const payloadOrderedMap = Object.fromEntries(
        listProducts.value.data.data.map((product: any, index: number) => [
            product.order != null
                ? product.order - 1
                : index,
            product.id
        ])
    )
    const payload = payloadKey === 'product_ids'
        ? { [payloadKey]: payloadValues }
        : { [payloadKey]: payloadOrderedMap }

    try {
        await axios.patch(
            route(
                props.data.route_sync_related_products.name,
                props.data.route_sync_related_products.parameters
            ),
            payload
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
            text:
                error?.response?.data?.message ||
                trans("Failed to reorder products"),
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

            <Button v-if="!props.data?.editable" label="Save" :disabled="!saveActive" @click="SaveOrder"   :loading="loadingOrder" type="save" />
        </div>

        <!-- MAIN CONTENT -->
        <div class="bg-white border rounded-lg p-4">

            <SetOrderingPositionOfProduct :data="listProducts.data" :disabled="props.data?.editable"
                @update:data="(event) => { listProducts.data.data = event, saveActive = true }"
                :useDelete="true" @delete="(item) => {
                    listProducts.data.data = listProducts.data.data.filter((product: any) => product.id !== item.id),
                    saveActive = true
                }">


                <template #image-list="{item}">
                    <Image :src="item.image_thumbnail?.main?.thumbnail" class="w-10 h-10 object-cover rounded" />
                </template>

                <template #image-card="{item}">
                    <Image :src="item.image_thumbnail?.main?.thumbnail" class="w-full h-24 object-cover rounded mb-2" />
                </template>

                <!-- TOP ACTION -->
                <template v-if="!props.data?.editable" #before-button-list>
                    <div class="flex justify-end mx-3">
                        <Button label="+ Add Product" type="tertiary" size="xs" @click="openAddProduct" />
                    </div>
                </template>

                <!-- EMPTY STATE -->
                <template #empty>
                    <div
                        class="flex flex-col items-center justify-center text-center py-12 px-6 border border-dashed rounded-lg bg-gray-50">

                        <div class="text-sm font-semibold text-gray-700">
                            {{ trans('No products found') }}
                        </div>

                        <div class="text-xs text-gray-500 mt-1 max-w-xs">
                            {{ trans('Start by adding your first product to this list.') }}
                        </div>

                        <Button v-if="props.data?.editable" class="mt-5" label="Add Product" type="create"
                            @click="openAddProduct" />
                    </div>
                </template>

            </SetOrderingPositionOfProduct>

        </div>

        <!-- SELECTOR -->
        <ListSelector
            v-if="props.data?.editable" ref="productDialog"
            v-model="listProducts.data.data"
            @update:model-value="saveActive = true"
            :routeFetch="props.data?.route_get_products"
        />

    </div>
</template>
