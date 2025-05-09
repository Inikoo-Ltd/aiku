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
import TablePortfolios from "@/Components/Tables/Grp/Org/Catalogue/TablePortfolios.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBrackets, faBracketsCurly, faFileExcel, faImage } from "@fal"

library.add(faFileExcel, faBracketsCurly, faImage)

// import FileShowcase from '@/xxxxxxxxxxxx'

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	tabs: TSTabs
	products: {}
	is_manual: boolean
	download_route: any
	order_route: routeType
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const productRoute = ref()
const selectedChildProducts = ref<any[]>([])
const tablePortfoliosRef = ref(null)
const orderMode = ref(false)

const onCreateOrder = () => {
	orderMode.value = true
}

const onCancelOrder = () => {
	orderMode.value = false
}

const component = computed(() => {
	const components: Component = {
		// showcase: FileShowcase
		// products: TableProducts
	}

	return components[currentTab.value]
})

const downloadUrl = (type: string) => {
	return route(props.download_route[type].name, props.download_route[type].parameters)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other="{ action }">
			<a :href="downloadUrl('csv')" rel="noopener">
				<Button
					:icon="faFileExcel"
					label="Download Excel"
					:style="'tertiary'" />
			</a>
			<a :href="downloadUrl('json')" rel="noopener">
				<Button
					:icon="faBracketsCurly"
					label="Download JSON"
					:style="'tertiary'" />
			</a>
			<a :href="downloadUrl('images')" rel="noopener">
				<Button
					:icon="faImage"
					label="Download Image"
					:style="'tertiary'" />
			</a>
			<Button
				v-if="!orderMode && is_manual"
				@click="onCreateOrder"
				:label="'Create Order'"
				:style="'create'" />
			<Button
				v-if="orderMode && is_manual"
				@click="onCancelOrder"
				:label="'Cancel'"
				:style="'cancel'" />
		</template>
	</PageHeading>

	<TablePortfolios
		:data="props.products"
		:tab="'products'"
		:is_manual
		:orderMode="orderMode"
		:order_route />
</template>
