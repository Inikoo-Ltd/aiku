<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SalesAnalysis from "@/Components/SalesAnalysis/SalesAnalysis.vue"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
	title: string
	pageHead: PageHeadingTypes
	sales_analysis?: object
}>()
</script>

<template>
	<Head :title="title" />
	<PageHeading :data="pageHead" />
	<SalesAnalysis :data="sales_analysis" />
</template>
