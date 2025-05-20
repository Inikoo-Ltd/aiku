<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, defineAsyncComponent, ref } from "vue"
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
	products: {}
	// is_manual: boolean
	// order_route: routeType
	routes: {
		syncAllRoute: routeType
		addPortfolioRoute: routeType
	}
}>()

// const currentTab = ref(props.tabs.current)
// const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
// const productRoute = ref()
// const selectedChildProducts = ref<any[]>([])
// const RetinatablePortfoliosRef = ref(null)
// const orderMode = ref(false)

// const onCreateOrder = () => {
// 	orderMode.value = true
// }

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
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<Button
				@click="isOpenModalPortfolios = true"
				:label="trans('Add portfolio')"
				:icon="'fas fa-plus'"
			/>
		</template>
	</PageHeading>

	<!-- <pre>{{ props.routes }}</pre> -->

	<div v-if="props.products?.data?.length < 1" class="relative mx-auto flex max-w-3xl flex-col items-center px-6 text-center pt-20 lg:px-0">
        <h1 class="text-4xl font-bold tracking-tight lg:text-6xl">
			You have no portfolios
		</h1>
        <p class="mt-4 text-xl">
			To get started, add products to your portfolios. You can sync from your inventory or create a new one.
		</p>
		<div class="mt-6 space-y-4">
			<ButtonWithLink
				:routeTarget="routes.syncAllRoute"
				isWithError
				label="Sync from Inventory"
				icon="fas fa-sync-alt"
				type="tertiary"
				size="xl"
			/>
			<div class="text-gray-500">or</div>
			<Button @click="isOpenModalPortfolios = true" :label="trans('Add portfolio')" icon="fas fa-plus" size="xl" />
		</div>
	</div>

	<RetinaTablePortfolios v-else :data="props.products" :tab="'products'" />

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
