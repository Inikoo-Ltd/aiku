<script setup lang="ts">
import { computed } from "vue"
import SearchResultGeneric from "@/Components/Search/SearchResultGeneric.vue"
import {
	firstProcurementSearchResultSection,
	prioritiseProcurementSearchResults,
} from "@/Components/Search/prioritiseProcurementSearchResults"
import type { ProcurementSearchResults } from "@/Components/Search/prioritiseProcurementSearchResults"

const model = defineModel<boolean>("open")

const props = defineProps<{
	results: ProcurementSearchResults | null
	isLoading: boolean
	query: string
}>()

const routeName = route().current() ?? ""
const prioritisedResults = computed(() =>
	prioritiseProcurementSearchResults(props.results, routeName)
)
const initialSection = computed(() => firstProcurementSearchResultSection(prioritisedResults.value))
</script>

<template>
	<SearchResultGeneric
		:key="initialSection"
		v-model:open="model"
		:results="prioritisedResults"
		:is-loading="isLoading"
		:query="query" />
</template>
