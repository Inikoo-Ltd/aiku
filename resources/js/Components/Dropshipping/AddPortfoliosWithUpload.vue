<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import Button from '../Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import { onMounted, ref, watch } from 'vue'
import { routeType } from '@/types/route'
import { set } from 'lodash'
import axios from 'axios'
import PortfoliosStepSyncShopify from '../Retina/Dropshipping/PortfoliosStepSyncShopify.vue'
import EmptyState from '../Utils/EmptyState.vue'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import PortfoliosStepEdit from '../Retina/Dropshipping/PortfoliosStepEdit.vue'
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

const recentlyUpdatedProduct = ref(null)

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
			props.step.current = 1
            // isOpenModalPortfolios.value = false
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


// Step 1: Submit
const progressToUploadToShopify = ref({})


const portfoliosList = ref([])
const stepLoading = ref(false)

const fetchIndexUnuploadedPortfolios = async () => {
	stepLoading.value = true
	const data = await axios.get(
		route('retina.dropshipping.customer_sales_channels.portfolios.index',
			{
				customerSalesChannel: route().params.customerSalesChannel,
				'filter[un_upload]': 'true',
			}
		)
	)
	portfoliosList.value = data.data.data
	stepLoading.value = false
}

watch(() => props.step.current, async (newStep, oldStep) => {
	// console.log('Step changed to:', oldStep, newStep)
	if (newStep === 1 || newStep === 2) {
		fetchIndexUnuploadedPortfolios()
	}
})

// Step 2: Update portfolios
const listState = ref({})
const updateSelectedProducts = async (portfolio: { id: number }, modelData: {}, section: string) => {
	set(listState.value, [portfolio.id, section], 'loading')


	try {
		const response = await axios[props.routes.updatePortfolioRoute.method || 'patch'](
			route(props.routes.updatePortfolioRoute.name,
				{
					portfolio: portfolio.id,
				}
			), modelData
		)

        // console.log('11111 Portfolio updated successfully:', response.data)
        recentlyUpdatedProduct.value = response.data
		set(listState.value, [portfolio.id, section], 'success')
	} catch (error) {
        console.log('Error updating portfolio:', error)
		set(listState.value, [portfolio.id, section], 'error')
	}

	setTimeout(() => {
		set(listState.value, [portfolio.id, section], null)
	}, 3000);
}

// Step 3: bulk upload to Shopify
const isLoadingBulkDeleteUpload = ref(false)
const selectedPortfoliosToSync = ref()
const bulkUpload = () => {
	router[props.routes.bulk_upload.method || 'post'](
		route(props.routes.bulk_upload.name, props.routes.bulk_upload.parameters),
		{
			portfolios: selectedPortfoliosToSync.value.map((product: any) => product.id),
		},
		{
			preserveScroll: true,
			// onBefore: () => isLoadingUpload.value = true,
			onStart: () => {
				isLoadingBulkDeleteUpload.value = true
			},
			onSuccess: () => {
				selectedPortfoliosToSync.value.forEach((product) => {
					set(progressToUploadToShopify.value, [product.id], 'loading')
				})
				selectedPortfoliosToSync.value = []
				notify({
					title: trans("Success!"),
					text: trans("Successfully uploaded portfolios"),
					type: "success",
				})
			},
			onFinish: () => {
				isLoadingBulkDeleteUpload.value = false
			},
			onError: (error) => {
				notify({
					title: trans("Something went wrong"),
					text: error.message || trans("An error occurred while uploading portfolios"),
					type: "error",
				})
			}
		}
	)
}
const bulkDelete = () => {
	router[props.routes.batchDeletePortfolioRoute.method || 'post'](
		route(props.routes.batchDeletePortfolioRoute.name, props.routes.batchDeletePortfolioRoute.parameters),
		{
			portfolios: selectedPortfoliosToSync.value.map((product: any) => product.id),
		},
		{
			preserveScroll: true,
			// onBefore: () => isLoadingUpload.value = true,
			onStart: () => {
				isLoadingBulkDeleteUpload.value = true
			},
			onSuccess: () => {
				// selectedPortfoliosToSync.value.forEach((product) => {
				// 	set(progressToUploadToShopify.value, [product.id], 'loading')
				// })
				portfoliosList.value = portfoliosList.value.filter(
					(portfolio) => !selectedPortfoliosToSync.value.some((p: any) => p.id === portfolio.id)
				)
				selectedPortfoliosToSync.value = []
				notify({
					title: trans("Success!"),
					text: trans("Deleted portfolios successfully"),
					type: "success",
				})
			},
			onFinish: () => {
				isLoadingBulkDeleteUpload.value = false
			},
			onError: (error) => {
				notify({
					title: trans("Something went wrong"),
					text: error.message || trans("An error occurred while uploading portfolios"),
					type: "error",
				})
			}
		}
	)
}

onMounted(() => {
    if (props.step.current > 0) {
        fetchIndexUnuploadedPortfolios()
    }
})
</script>

<template>
    <div>
        <!-- Head: step 0 -->
        <div v-if="step.current === 0" class="grid grid-cols-4 mb-4">
            <div class="relative">
            </div>
            <div class="col-span-2 mx-auto text-center text-2xl font-semibold pb-4">
                {{ trans('Add products to portfolios') }}
            </div>
            <div class="relative text-right">
                <Button
                    v-if="step.current == 0"
                    @click="step.current = 1"
                    :disabled="isLoadingSubmit"
                    :label="trans('Skip to edit products')"
                    iconRight="fal fa-arrow-right"
                    type="tertiary"
                />
            </div>
        </div>
        <!-- Head: step 1 -->
        <div v-if="step.current == 1" class="grid grid-cols-4">
            <div class="relative">
                <Button
                    v-if="step.current == 1"
                    @click="step.current = 0"
                    :label="trans('Add portfolios')"
                    icon="fal fa-arrow-left"
                    type="tertiary"
                />
            </div>
            <div class="text-center col-span-2">
                <div class="font-bold text-2xl">{{ trans("Edit portfolios") }}</div>
                <div class="text-gray-500 text-sm italic tracking-wide">
                    <!-- {{ trans("Edit the portfolios before syncing them to Shopify if needed") }} -->
                    {{ `Edit the portfolios before syncing them to ${platform_data.name} if needed` }}
                </div>
            </div>
            <div class="relative text-right">
                <!-- <Button
                    v-if="step.current == 1"
                    @click="step.current = 2"
                    :label="trans('Sync to Shopify')"
                    :iconRight="faArrowRight"
                    type="tertiary"
                /> -->
            </div>
        </div>
        <!-- Head: step 2 -->
        <div v-if="step.current == 2" class="grid grid-cols-4">
            <div class="relative">
                <Button
                    v-if="step.current == 2"
                    @click="step.current = 1"
                    :label="trans('Edit products')"
                    icon="fal fa-arrow-left"
                    type="tertiary"
                />
            </div>
            <div class="text-center col-span-2">
                <div class="font-bold text-2xl">{{ `Sync to ${platform_data.name}` }}</div>
                <div class="text-gray-500 text-sm italic tracking-wide">
                    {{ trans("You can select them via checkbox to bulk syncing or sync 1 by 1.") }}
                </div>
            </div>
            <div class="relative space-x-2 space-y-1 text-right">
                <!-- Button: bulk upload -->
                <Button
                    v-if="selectedPortfoliosToSync?.length"
                    @click="() => bulkDelete()"
                    :label="trans('Remove portfolios') + ' (' + selectedPortfoliosToSync?.length + ')'"
                    type="delete"
                    size="s"
                    :loading="isLoadingBulkDeleteUpload"
                />
                <!-- Button: bulk upload -->
                <Button
                    v-if="selectedPortfoliosToSync?.length"
                    @click="() => bulkUpload()"
                    xlabel="trans('Sync to Shopify') + ' (' + selectedPortfoliosToSync?.length + ')'"
                    :label="`Sync to ${platform_data.name} (${selectedPortfoliosToSync?.length})`"
                    icon="fal fa-upload"
                    size="s"
                    type="positive"
                    :loading="isLoadingBulkDeleteUpload"
                />
            </div>
        </div>

        <!-- 0: Select Product -->
        <KeepAlive>
            <ProductsSelector
                v-if="step.current === 0"
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
            </ProductsSelector>
        </KeepAlive>

        <!-- 1: Edit Product -->
        <KeepAlive>
            <div v-if="step.current === 1">
                <div class="relative px-4 h-[600px] mt-4 overflow-y-auto mb-4">
                    <div v-if="stepLoading" class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-50 text-7xl">
                        <LoadingIcon />
                    </div>
                    <PortfoliosStepEdit
                        v-else-if="portfoliosList?.length"
                        :portfolios="portfoliosList"
                        @updateSelectedProducts="updateSelectedProducts"
                        :listState
                        amounted="() => fetchIndexUnuploadedPortfolios()"
                        :recentlyUpdatedProduct
                    />
                    <EmptyState
                        v-else
                        :data="{
                            title: trans('No portfolios selected'),
                        }"
                    />
                </div>
                <div v-if="portfoliosList?.length" class="border-t border-gray-300 pt-4 w-full">
                    <Button
                        v-if="step.current == 1"
                        @click="step.current = 2"
                        xlabel="trans('Next step (sync to Shopify)')"
                        :label="`Next step (sync to ${platform_data.name})`"
                        full
                        iconRight="fal fa-arrow-right"
                        type="primary"
                    />
                </div>
            </div>
        </KeepAlive>
        
        <!-- 2: Upload product to Shopify -->
        <KeepAlive>
            <div v-if="step.current === 2">
                <div class="px-4 h-[600px] mt-4 overflow-y-auto mb-4">
                    <div v-if="stepLoading" class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-50 text-7xl">
                        <LoadingIcon />
                    </div>
                    <PortfoliosStepSyncShopify
                        v-else-if="portfoliosList?.length"
                        :portfolios="portfoliosList"
                        :listState
                        v-model="selectedPortfoliosToSync"
                        @updateSelectedProducts="updateSelectedProducts"
                        @portfolioDeleted="(portfolio) => portfoliosList.splice(portfoliosList.indexOf(portfolio), 1)"
                        amounted="() => fetchIndexUnuploadedPortfolios()"
                        :progressToUploadToShopify
                        :platform_data
                        :platid="props.platform_user_id"
                    />
                    <EmptyState
                        v-else
                        :data="{
                            title: trans('No portfolios selected'),
                        }"
                    />
                </div>
                <div class="border-t border-gray-300 pt-4 w-full">
                    <Button
                        @click="emits('onDone')"
                        :label="trans('Done & close')"
                        full
                        xxiconRight="faArrowRight"
                        type="tertiary"
                    />
                </div>
            </div>
        </KeepAlive>
    </div>
</template>