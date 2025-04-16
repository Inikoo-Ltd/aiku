<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, defineAsyncComponent, ref } from "vue"
import type { Component } from "vue"

import { PageHeading as TSPageHeading } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import TablePortfolios from "@/Components/Tables/Grp/Org/Catalogue/TablePortfolios.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"

// import FileShowcase from '@/xxxxxxxxxxxx'

const props = defineProps<{
	title: string
	pageHead: TSPageHeading
	tabs: TSTabs
	products: {}
	is_manual: boolean
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
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
	
		<template #other="{ action }">
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
		</template>
	</PageHeading>
	<!--     <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />-->
	<!--     <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />-->
	<TablePortfolios :data="props.products" :tab="'products'" :is_manual :orderMode="orderMode" :order_route />
</template>
