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
		upload_route?: routeType
		itemRoute: routeType
		updatePortfolioRoute: routeType
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


// Section: Upload to Shopify
const selectedData = reactive({
	products: [] as number[],
})
const isLoadingUpload = ref(false)
const onUploadToShopify = () => {
	if (!props.routes.upload_route?.name) {
		notify({
			title: trans("No route defined"),
			type: "error",
		})
		return
	}

	router.post(route(props.routes.upload_route.name, props.routes.upload_route.parameters), {
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
		},
		onFinish: () => {
			isLoadingUpload.value = false
		}
	})
}

const currentStep = ref(2)
const selectedProducts = ref()

const portfoliosList = ref([])
const stepLoading = ref(false)

const fetchIndexUnuploadedPortfolios = async () => {
	stepLoading.value = true
	const data = await axios.get(
		route('retina.dropshipping.customer_sales_channels.portfolios.index',
			{
				...route().params,
				'filter[unupload]': 'true',
			}
		)
	)
	portfoliosList.value = data.data.data
	stepLoading.value = false
}

watch(currentStep, async (newStep) => {
	if (newStep === 1 || newStep === 2) {
		fetchIndexUnuploadedPortfolios()
	}
})

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
				@click="() => (isOpenModalPortfolios = true, currentStep > 0 ? fetchIndexUnuploadedPortfolios() : null)"
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

	<Modal :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false" width="w-full max-w-6xl max-h-[85vh] overflow-y-auto">
		<div v-if="currentStep == 2" class="flex justify-between">
			<div class="relative">
				<Button
					v-if="currentStep == 2"
					@click="currentStep = 1"
					:label="trans('Edit products')"
					:icon="faArrowLeft"
					type="tertiary"
				/>
			</div>

			<div class="relative">
				<Button
					v-if="currentStep == 2 && selectedProducts?.length"
					aclick="currentStep = 2"
					:label="trans('Remove portfolios') + ' (' + selectedProducts.length + ')'"
					xicon="faUpload"
					type="delete"
				/>
				
				<Button
					v-if="currentStep == 2 && selectedProducts?.length"
					aclick="currentStep = 2"
					:label="trans('Sync to Shopify') + ' (' + selectedProducts.length + ')'"
					:icon="faUpload"
					type="secondary"
				/>
			</div>
		</div>

		<div v-if="currentStep == 1" class="flex justify-between">
			<div class="relative">
				<Button
					v-if="currentStep == 1"
					@click="currentStep = 0"
					:label="trans('Add portfolios')"
					:icon="faArrowLeft"
					type="tertiary"
				/>
			</div>

			<div class="relative">

				<Button
					v-if="currentStep == 1"
					@click="currentStep = 2"
					:label="trans('Sync to Shopify')"
					:iconRight="faArrowRight"
					type="tertiary"
				/>
			</div>
		</div>
		
		<div v-if="currentStep === 0" class="flex justify-between">
			<div class="relative">
			</div>

			<div class="relative">
				<Button
					v-if="currentStep == 0"
					@click="currentStep = 1"
					:label="trans('Skip to edit products')"
					:iconRight="faArrowRight"
					type="tertiary"
				/>
			</div>
		</div>

		<!-- 1: Select Product -->
        <KeepAlive>
			<ProductsSelector
				v-if="currentStep === 0"
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
			<div v-if="currentStep === 1">
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

				<Button
					@click="currentStep = 2"
					label="Submit & Go next step"
					full
				/>
			</div>
		</KeepAlive>

		<!-- 3: Upload product to Shopify -->
		<KeepAlive>
			<div v-if="currentStep === 2">
				<div class="px-4 h-[600px] mt-4 overflow-y-auto mb-4">
					<div v-if="stepLoading" class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-50 text-7xl">
						<LoadingIcon />
					</div>
					
					<PortfoliosStepSyncShopify
						v-else-if="portfoliosList?.length"
						:portfolios="portfoliosList"
						:listState
						v-model="selectedProducts"
						@updateSelectedProducts="updateSelectedProducts"
						amounted="() => fetchIndexUnuploadedPortfolios()"
					/>

					<EmptyState
						v-else
						:data="{
							title: trans('No portfolios selected'),
						}"
					/>

				</div>

				<Button
					@click="currentStep = 2"
					label="Submit & close"
					full
				/>
			</div>
		</KeepAlive>
    </Modal>
</template>
