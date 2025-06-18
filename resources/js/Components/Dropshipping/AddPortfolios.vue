<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import Button from '../Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import { onMounted, ref, watch } from 'vue'
import { routeType } from '@/types/route'
// import axios from 'axios'
import ProductsSelector from './ProductsSelector.vue'

const props = defineProps<{
    step: {
        current: number
    }
    routes: {
		syncAllRoute: routeType
		addPortfolioRoute: routeType
		bulk_upload: routeType
		itemRoute: routeType
		updatePortfolioRoute: routeType
		batchDeletePortfolioRoute: routeType
	}
    platform_data: {
		id: number
		code: string
		name: string
		type: string
	}
    platform_user_id: number
}>()


const emits = defineEmits<(e: "onDone") => void>()


// Section: Add portfolios
const isLoadingSubmit = ref(false)
const onSubmitAddPortfolios = async (idProduct: number[]) => {
    router.post(route(props.routes.addPortfolioRoute.name, props.routes.addPortfolioRoute.parameters), {
        items: idProduct
    }, {
        onBefore: () => isLoadingSubmit.value = true,
        onError: (error) => {
            notify({
                title: trans("Something went wrong."),
                text: error.products || undefined,
                type: "error"
            })
        },
        onSuccess: () => {
            router.reload({only: ['pageHead', 'products']})
            notify({
                title: trans("Success!"),
                text: trans("Successfully added portfolios"),
                type: "success"
            })
            emits("onDone")
        },
        onFinish: () => isLoadingSubmit.value = false
    })
}


// Filter portfolios by type
const filterList = [
    {
        label: trans("Product"),
        value: "product",
    },
	{
		label: trans("Department"),
		value: "department",
	},
	{
		label: trans("Sub-department"),
		value: "sub_department",
	},
	{
		label: trans("Family"),
		value: "family",
	}
]
const selectedList = ref(filterList[0])

// const portfoliosList = ref([])
// const stepLoading = ref(false)

// const fetchIndexUnuploadedPortfolios = async () => {
// 	stepLoading.value = true
// 	const data = await axios.get(
// 		route('retina.dropshipping.customer_sales_channels.portfolios.index',
// 			{
// 				customerSalesChannel: route().params.customerSalesChannel,
// 				'filter[un_upload]': 'true',
// 			}
// 		)
// 	)
// 	portfoliosList.value = data.data.data
// 	stepLoading.value = false
// }

// watch(() => props.step.current, async (newStep, oldStep) => {
// 	// console.log('Step changed to:', oldStep, newStep)
// 	if (newStep === 1 || newStep === 2) {
// 		fetchIndexUnuploadedPortfolios()
// 	}
// })

// onMounted(() => {
//     fetchIndexUnuploadedPortfolios()
// })
</script>

<template>
    <div>
        <!-- Head: step 0 -->
        <div class="grid grid-cols-4 mb-4">
            <div class="relative">
            </div>
            <div class="col-span-2 mx-auto text-center text-2xl font-semibold pb-4">
                {{ trans('Add products to portfolios') }}
            </div>
        </div>

        <!-- 0: Select Product -->
        <KeepAlive>
            <ProductsSelector
                :headLabel="trans('Add products to portfolios')"
                :route-fetch="{
                    name: props.routes.itemRoute.name,
                    parameters: {
                        ...props.routes.itemRoute.parameters,
                        'filter[type]': selectedList.value,
                    },
                }"
                :valueToRefetch="selectedList.value"
                :label_result="selectedList.label"
                :isLoadingSubmit
                @submit="(products: {}[]) => onSubmitAddPortfolios(products.map((product: any) => product.id))"
                class="px-4"
            >
                <template #header>
                    <div>
                    </div>
                </template>

                <template #afterInput>
                    <div class="flex gap-2 text-sm font-semibold text-gray-500 mt-2 max-w-sm">
                        <div v-for="list in filterList"
                            @click="selectedList = list"
                            class="whitespace-nowrap py-2 px-3 cursor-pointer rounded border "
                            :class="selectedList.value === list.value ? 'bg-gray-700 text-white border-gray-400' : 'border-gray-300 hover:bg-gray-200'"
                        >
                            {{ list.label}}
                        </div>
                    </div>
                </template>

                <template #bottom-button="{ selectedProduct, isLoadingSubmit }">
                    <div class="mt-4">
                        <Button
                            @click="() => onSubmitAddPortfolios(selectedProduct.map((product: any) => product.id))"
                            :disabled="selectedProduct.length < 1"
                            v-tooltip="selectedProduct.length < 1 ? trans('Select at least one product') : ''"
                            :label="`${trans('Add')} ${selectedProduct.length} and close`"
                            type="primary"
                            full
                            icon="fas fa-plus"
                            :loading="isLoadingSubmit"
                        />
                    </div>
                </template>
            </ProductsSelector>
        </KeepAlive>
    </div>
</template>