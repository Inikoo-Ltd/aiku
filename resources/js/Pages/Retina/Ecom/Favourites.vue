<!--
* Author: Vika Aqordi
* Created on: 18-02-2025-09h-16m
* Github: https://github.com/aqordeon
* Copyright: 2025
-->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHeart } from "@fas"
import { faBoxOpen, faImage } from "@fal"
import { GridProducts } from "@/Components/Product"
library.add(faHeart, faBoxOpen, faImage)
const props = defineProps<{
	data: {}
	title: string
	pageHead: {}
}>()

</script>

<template>

	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"/>
	<!-- Pass the data to GridProducts component -->
	<GridProducts 
		:resource="data" 
		:preserve-scroll="true"
		class="mt-5"
	/>

</template>
