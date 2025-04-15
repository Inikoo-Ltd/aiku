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

// import FileShowcase from '@/xxxxxxxxxxxx'

const props = defineProps<{
	title: string
	pageHead: TSPageHeading
	tabs: TSTabs
	products: {}
	is_manual: boolean
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const productRoute = ref()
const selectedChildProducts = ref<any[]>([])

const handleUpdateSelected = (selected: any[]) => {
	console.log("selected", selected)

	selectedChildProducts.value = selected
}

const component = computed(() => {
	const components: Component = {
		// showcase: FileShowcase
		// products: TableProducts
	}

	return components[currentTab.value]
})

const onSubmitProduct = (action: any) => {
	console.log("selectedChildProducts", selectedChildProducts.value)

	// Send the products as an array of objects: { id, quantity }
	router.post(
		route(action.route.name, action.route.parameters),
		{
			products: selectedChildProducts.value,
		},
		{
			headers: {
				Authorization: `Bearer ${window.sessionToken}`,
				"Content-Type": "application/x-www-form-urlencoded",
			},
			onStart: () => {
				/* isLoadingSubmit.value = true */
			},
			onSuccess: () => {
				notify({
					title: trans("Success"),
					text:
						trans("Successfully added") +
						` ${selectedChildProducts.value.length} ` +
						trans("products"),
					type: "success",
				})
				selectedChildProducts.value = []
			},
			onError: () => {
				notify({
					title: trans("Failed"),
					text: trans("Something went wrong. Try again."),
					type: "error",
				})
			},
			onFinish: () => {
				/* isLoadingSubmit.value = false */
			},
		}
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-create-order="{ action }">
			<Button
				@click="() => onSubmitProduct(action)"
				:label="action.label"
				:icon="action.icon" />
		</template>
	</PageHeading>
	<!--     <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />-->
	<!--     <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />-->
	<TablePortfolios
		:data="props.products"
		:tab="'products'"
		:is_manual
		@update-selectedProducts="handleUpdateSelected" />
</template>
