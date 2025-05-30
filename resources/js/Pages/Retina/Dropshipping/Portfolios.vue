<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { reactive, ref, watch } from "vue"
import type { Component } from "vue"

import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import RetinaTablePortfolios from "@/Components/Tables/Retina/RetinaTablePortfolios.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSyncAlt } from "@fas"
import { faArrowLeft, faArrowRight, faUpload } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Modal from "@/Components/Utils/Modal.vue"
import ProductsSelector from "@/Components/Dropshipping/ProductsSelector.vue"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import axios from "axios"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { set } from "lodash"
import PortfoliosStepEdit from "@/Components/Retina/Dropshipping/PortfoliosStepEdit.vue"
import PortfoliosStepSyncShopify from "@/Components/Retina/Dropshipping/PortfoliosStepSyncShopify.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
library.add(faSyncAlt)

// import FileShowcase from '@/xxxxxxxxxxxx'

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	tabs: TSTabs
    content?: {
        portfolio_empty?: {
			title?: string,
			description?: string,
			separation?: string,
			sync_button?: string,
			add_button?: string
		}
    }
	products: {}
	// is_manual: boolean
	// order_route: routeType
	routes: {
		syncAllRoute: routeType
		addPortfolioRoute: routeType
		bulk_upload: routeType
		itemRoute: routeType
		updatePortfolioRoute: routeType
	}
	platform_user_id: {

	}
	step: {
		current: number
	}
}>()

const locale = inject('locale', aikuLocaleStructure)

// const onCancelOrder = () => {
// 	orderMode.value = false
// }

// const component = computed(() => {
// 	const components: Component = {
// 		// showcase: FileShowcase
// 		// products: TableProducts
// 	}

// 	return components[currentTab.value]
// })

// Section: Add portfolios
const isOpenModalPortfolios = ref(false)
const isLoadingSubmit = ref(false)
const onSubmitAddItem = async (idProduct: number[]) => {
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
const selectedData = reactive({
	products: [] as number[],
})
const isLoadingUpload = ref(false)
const progressToUploadToShopify = ref({})
const onUploadToShopify = () => {
	if (!props.routes.bulk_upload?.name) {
		notify({
			title: trans("No route defined"),
			type: "error",
		})
		return
	}

	router.post(route(props.routes.bulk_upload.name, props.routes.bulk_upload.parameters), {
		portfolios: selectedData.products,
	}, {
		preserveScroll: true,
		onBefore: () => isLoadingUpload.value = true,
		onError: (error) => {
			notify({
				title: trans("Something went wrong"),
				text: "",
				type: "error",
			})
		},
		onSuccess: () => {
			selectedData.products = []
			router.reload({ only: ['pageHead', 'products'] })
			notify({
				title: trans("Success!"),
				text: trans("Portfolios successfully uploaded to Shopify"),
				type: "success",
			})
			props.step.current = 1
		},
		onFinish: () => {
			isLoadingUpload.value = false
		}
	})
}


const portfoliosList = ref([])
const stepLoading = ref(false)

const fetchIndexUnuploadedPortfolios = async () => {
	stepLoading.value = true
	const data = await axios.get(
		route('retina.dropshipping.customer_sales_channels.portfolios.index',
			{
				customerSalesChannel: route().params.customerSalesChannel,
				'filter[unupload]': 'true',
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
		const data = await axios[props.routes.updatePortfolioRoute.method || 'patch'](
			route(props.routes.updatePortfolioRoute.name,
				{
					portfolio: portfolio.id,
				}
			), modelData
		)
		set(listState.value, [portfolio.id, section], 'success')
	} catch (error) {
		set(listState.value, [portfolio.id, section], 'error')
	}

	setTimeout(() => {
		set(listState.value, [portfolio.id, section], null)
	}, 3000);
}

// Step 3: bulk upload to Shopify
const selectedPortfoliosToSync = ref()
const bulkUpload = () => {
	router[props.routes.bulk_upload.method || 'post'](
		route(props.routes.bulk_upload.name, props.routes.bulk_upload.parameters),
		{
			portfolios: selectedPortfoliosToSync.value.map((product: any) => product.id),
		},
		{
			preserveScroll: true,
			onBefore: () => isLoadingUpload.value = true,
			onStart: () => {
				
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
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-upload-to-shopify="{ action }">
			<Button
				@click="onUploadToShopify()"
				:style="action.style"
				:label="action.label"
				:loading="isLoadingUpload"
				:disabled="!selectedData.products.length"
				v-tooltip="!selectedData.products.length ? trans('Select at least one product to upload') : ''"
			/>
		</template>

		<template v-if="props.products?.data?.length" #other>
			<Button
				@click="() => (isOpenModalPortfolios = true, step.current > 0 ? fetchIndexUnuploadedPortfolios() : null)"
				:label="trans('Add portfolio')"
				:icon="'fas fa-plus'"
			/>
		</template>
	</PageHeading>


	<div v-if="props.products?.data?.length < 1" class="relative mx-auto flex max-w-3xl flex-col items-center px-6 text-center pt-20 lg:px-0">
        <h1 class="text-4xl font-bold tracking-tight lg:text-6xl">
            {{ content?.portfolio_empty?.title || trans(`You don't have a single portfolios`) }}
		</h1>
        <p class="mt-4 text-xl">
			{{ content?.portfolio_empty?.description || trans('To get started, add products to your portfolios. You can sync from your inventory or create a new one.') }}
		</p>
		<div class="mt-6 space-y-4">
			<ButtonWithLink
				v-if="routes?.syncAllRoute"
				:routeTarget="routes?.syncAllRoute"
				isWithError
				:label="content?.portfolio_empty?.sync_button"
				icon="fas fa-sync-alt"
				xtype="tertiary"
				size="xl"
			/>
			<div v-if="routes?.syncAllRoute && routes?.addPortfolioRoute" class="text-gray-500">{{ content?.portfolio_empty?.separation || trans('or') }}</div>
			<Button v-if="routes?.addPortfolioRoute" @click="isOpenModalPortfolios = true" :label="content?.portfolio_empty?.add_button || trans('Add portfolio')" icon="fas fa-plus" size="xl" />
		</div>
	</div>

	<RetinaTablePortfolios v-else :data="props.products" :tab="'products'" :selectedData />

	<Modal :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false" width="w-full max-w-7xl max-h-[85vh] overflow-y-auto">
		<div v-if="step.current === 0" class="flex justify-between">
			<div class="relative">
			</div>
			

			<div class="relative">
				<Button
					v-if="step.current == 0"
					@click="step.current = 1"
					:label="trans('Skip to edit products')"
					:iconRight="faArrowRight"
					type="tertiary"
				/>
			</div>
		</div>

		<div v-if="step.current == 1" class="grid grid-cols-4">
			<div class="relative">
				<Button
					v-if="step.current == 1"
					@click="step.current = 0"
					:label="trans('Add portfolios')"
					:icon="faArrowLeft"
					type="tertiary"
				/>
			</div>

			<div class="text-center col-span-2">
				<div class="font-bold text-2xl">{{ trans("Edit portfolios") }}</div>
				<div class="text-gray-500 text-sm italic tracking-wide">
					{{ trans("Edit the portfolios before syncing them to Shopify if needed") }}
				</div>
			</div>

			<div class="relative text-right">
				<Button
					v-if="step.current == 1"
					@click="step.current = 2"
					:label="trans('Sync to Shopify')"
					:iconRight="faArrowRight"
					type="tertiary"
				/>
			</div>
		</div>
		

		<div v-if="step.current == 2" class="grid grid-cols-4">
			<div class="relative">
				<Button
					v-if="step.current == 2"
					@click="step.current = 1"
					:label="trans('Edit products')"
					:icon="faArrowLeft"
					type="tertiary"
				/>
			</div>

			<div class="text-center col-span-2">
				<div class="font-bold text-2xl">{{ trans("Sync to Shopify") }}</div>
				<div class="text-gray-500 text-sm italic tracking-wide">
					{{ trans("You can select them via checkbox to bulk syncing or sync 1 by 1.") }}
				</div>
			</div>

			<div class="relative space-x-2 space-y-1 text-right">
				<!-- <Button
					v-if="step.current == 2 && selectedPortfoliosToSync?.length"
					aclick="step.current = 2"
					:label="trans('Remove portfolios') + ' (' + selectedPortfoliosToSync.length + ')'"
					xicon="faUpload"
					type="delete"
				/>

				<ButtonWithLink
					:routeTarget="data.delete_portfolio"
					:label="trans('Remove portfolios') + ' (' + selectedPortfoliosToSync.length + ')'"
					type="delete"
					size="xs"
					@success="() => portfolios.splice(portfolios.indexOf(data), 1)"
				/> -->
				
				<Button
					v-if="step.current == 2 && selectedPortfoliosToSync?.length"
					@click="() => bulkUpload()"
					:label="trans('Sync to Shopify') + ' (' + selectedPortfoliosToSync.length + ')'"
					:icon="faUpload"
					type="secondary"
				/>
			</div>
		</div>

		<!-- 1: Select Product -->
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
				:label_result="selectedList.label"
				:isLoadingSubmit
				@submit="(products: {}[]) => onSubmitAddItem(products.map((product: any) => product.id))"
				class="px-4"
			>
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

		<!-- 2: Edit Product -->
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
					/>

					<EmptyState
						v-else
						:data="{
							title: trans('No portfolios selected'),
						}"
					/>
				</div>

				<!-- <Button
					@click="step.current = 2"
					label="Submit & Go next step"
					full
				/> -->
			</div>
		</KeepAlive>

		<!-- 3: Upload product to Shopify -->
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
						xxplatid="props.platform_user_id"
						v-model="selectedPortfoliosToSync"
						@updateSelectedProducts="updateSelectedProducts"
						amounted="() => fetchIndexUnuploadedPortfolios()"
						:progressToUploadToShopify
					/>

					<EmptyState
						v-else
						:data="{
							title: trans('No portfolios selected'),
						}"
					/>

				</div>

				<!-- <Button
					@click="step.current = 2"
					label="Submit & close"
					full
				/> -->
			</div>
		</KeepAlive>
    </Modal>
</template>
