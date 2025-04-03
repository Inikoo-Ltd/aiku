<script setup lang="ts">
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableFulfilmentCustomerPlatformOrders from "@/Components/Tables/Grp/Org/Fulfilment/TableFulfilmentCustomerPlatformOrders.vue"
import { Head, router } from "@inertiajs/vue3"
import { PageHeading as PageHeadingTS } from "@/types/PageHeading"
import { capitalize } from "@/Composables/capitalize"
import Modal from "@/Components/Utils/Modal.vue"
import { trans } from "laravel-vue-i18n"
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"

const props = defineProps<{
	data: {}
	title: string
	pageHead: object
	tabs: {
		current: string
		navigation: {}
	}
	platforms: {
		data: any
	}
    attachRoute:{}
}>()


</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"/>
	<TableFulfilmentCustomerPlatformOrders :data="data" />

</template>
