<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { reactive, ref, watch } from "vue"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import RetinaTablePortfolios from "@/Components/Tables/Retina/RetinaTablePortfolios.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { faSyncAlt } from "@fas"
import { faArrowLeft, faArrowRight, faUpload, faBox } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Modal from "@/Components/Utils/Modal.vue"
import ProductsSelector from "@/Components/Dropshipping/ProductsSelector.vue"
import axios from "axios"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { set } from 'lodash-es'
import PortfoliosStepEdit from "@/Components/Retina/Dropshipping/PortfoliosStepEdit.vue"
import PortfoliosStepSyncShopify from "@/Components/Retina/Dropshipping/PortfoliosStepSyncShopify.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import AddPortfoliosWithUpload from "@/Components/Dropshipping/AddPortfoliosWithUpload.vue"
import AddPortfolios from "@/Components/Dropshipping/AddPortfolios.vue"
library.add(faSyncAlt, faBox, faArrowLeft, faArrowRight, faUpload)


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
	routes: {
		syncAllRoute: routeType
		addPortfolioRoute: routeType
		bulk_upload: routeType
		itemRoute: routeType
		updatePortfolioRoute: routeType
		batchDeletePortfolioRoute: routeType
	}
	platform_user_id: number
	step: {
		current: number
	}
	platform_data: {
		id: number
		code: string
		name: string
		type: string
	}
}>()


console.log('platform', props.platform_data)
const step = ref(props.step)

const isOpenModalPortfolios = ref(false)



const isLoadingUpload = ref(false)
const selectedData = reactive({
	products: [] as number[],
})
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
				// text: trans("Portfolios successfully uploaded to Shopify"),
				text: `Portfolios successfully uploaded to ${props.platform_data.name}`,
				type: "success",
			})
			props.step.current = 1
		},
		onFinish: () => {
			isLoadingUpload.value = false
		}
	})
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
				@click="() => (isOpenModalPortfolios = true)"
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

	<Modal :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false" width="w-full max-w-7xl max-h-[85vh] overflow-y-auto py-43">
		<AddPortfolios
			v-if="platform_data?.type === 'manual'"
			:step="step"
			:routes="props.routes"
			:platform_data
			@onDone="isOpenModalPortfolios = false"
			:platform_user_id
		/>

		<AddPortfoliosWithUpload
			v-else
			:step="step"
			:routes="props.routes"
			:platform_data
			@onDone="isOpenModalPortfolios = false"
			:platform_user_id
		/>
    </Modal>
</template>
