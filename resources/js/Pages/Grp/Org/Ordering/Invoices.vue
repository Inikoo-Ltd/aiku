<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableInvoices from "@/Components/Tables/Grp/Org/Accounting/TableInvoices.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import type { PageHeadingTypes } from "@/types/PageHeading"

type InvoiceTab = "all" | "paid" | "unpaid"

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	tabs: {
		current: InvoiceTab
		navigation: object
	}
	all?: object
	paid?: object
	unpaid?: object
}>()

const currentTab = ref<InvoiceTab>(props.tabs.current)
const currentInvoices = computed(() => props[currentTab.value] ?? {})

const handleTabUpdate = (tab: string) => {
	useTabChange(tab, currentTab)
}
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead" />
		<Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
		<TableInvoices :data="currentInvoices" :tab="currentTab" />
	</div>
</template>
