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
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<!-- <template #other="{ action }">
			<Button
				v-if="!orderMode && is_manual"
				@click="onCreateOrder"
				:label="'Create Order'"
				:style="'create'"
				 />
			<Button
				v-if="orderMode && is_manual"
				@click="onCancelOrder"
				:label="'Cancel'"
				:style="'cancel'"
			 />
		</template> -->
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
			<Button label="Add portfolio" icon="fas fa-plus" size="xl" />
		</div>
	</div>

	<!--     <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />-->
	<!--     <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />-->
	<RetinaTablePortfolios v-else :data="props.products" :tab="'products'" />
</template>
