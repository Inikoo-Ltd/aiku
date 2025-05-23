<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { reactive, ref } from "vue"
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
import { library } from "@fortawesome/fontawesome-svg-core"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Modal from "@/Components/Utils/Modal.vue"
import ProductsSelector from "@/Components/Dropshipping/ProductsSelector.vue"
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
	}
}>()


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
            isOpenModalPortfolios.value = false
        },
        onFinish: () => isLoadingSubmit.value = false
    })
}

const selectedData = reactive({
	products: [] as number[],
})
</script>

<template>
<!-- {{ selectedData.products }} -->
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-upload-to-shopify="{ action }">
			<!-- <pre>{{ action }}</pre> -->
			<ButtonWithLink
				:routeTarget="action.route"
				method="post"
				:style="action.style"
				:body="{
					portfolios: selectedData.products,
				}"
				:label="action.label"
				:disabled="!selectedData.products.length"
				@success="() => selectedData.products = []"
				:bindToLink="{
					preserveScroll: true,
				}"
				isWithError
			/>
		</template>

		<template v-if="props.products?.data?.length" #other>
			<Button
				@click="isOpenModalPortfolios = true"
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

	<Modal :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false" width="w-full max-w-6xl">
        <ProductsSelector
            :headLabel="trans('Add products to portfolios')"
            :route-fetch="props.routes.itemRoute"
            :isLoadingSubmit
            @submit="(products: {}[]) => onSubmitAddItem(products.map((product: any) => product.id))"
        >
        </ProductsSelector>
    </Modal>
</template>
