<!--
    -  Author: Raul Perusquia <raul@inikoo.com>
    -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
    -  Copyright (c) 2022, Raul A Perusquia Flores
    -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableCustomerClients from "@/Components/Tables/Grp/Org/CRM/TableCustomerClients.vue"
import { capitalize } from "@/Composables/capitalize"
import Button from "@/Components/Elements/Buttons/Button.vue"
import UploadExcel from "@/Components/Upload/UploadExcel.vue"
import { ref } from "vue"

const props = defineProps<{
	data: {}
	title: string
	pageHead: {}
    upload_route : object
}>()

const isModalUploadOpen = ref(false)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other="{ action }">
			<Button
				@click="() => (isModalUploadOpen = true)"
				:label="'Upload File'"
				:style="'upload'" />
		</template>
	</PageHeading>
	<TableCustomerClients :data="data" />

	<UploadExcel
		v-model="isModalUploadOpen"
		scope="Supplier Product"
		:title="{
			label: 'Upload your new products',
			information: 'The list of column file: customer_reference, notes, stored_items',
		}"
        progressDescription="Adding Products to Supplier"        
        :upload_spreadsheet="upload_route" />
</template>
