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
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"

const props = defineProps<{
	data: {}
	title: string
	pageHead: {}
    upload_spreadsheet : object
}>()

const isModalUploadOpen = ref(false)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-create-customer-client="{ action }">
			<div class="flex items-center border border-gray-300 rounded-md divide-x divide-gray-300">
				<Button
					v-if="upload_spreadsheet"
					@click="() => (isModalUploadOpen = true)"
					:label="'Upload File'"
					xstyle="'upload'"
					type="tertiary"
					icon="fas fa-upload"
					class="rounded-none border-0"
				/>
				<ButtonWithLink
					:routeTarget="action.route"
					:label="action.label"
					type="tertiary"
					icon="fas fa-plus"
					buttonClass="rounded-none border-none"
				/>
			</div>
		</template>
	</PageHeading>
	<TableCustomerClients :data="data" />

	<UploadExcel
		v-if="upload_spreadsheet"
		v-model="isModalUploadOpen"
		scope="Supplier Product"
		:title="{
			label: 'Import your clients',
			information: 'The list of column file: contact_name, company_name, email, phone, address_line_1, address_line_2, postal_code, locality, country_code',
		}"
        progressDescription="Adding Products to Supplier"        
        :upload_spreadsheet="upload_spreadsheet" />
</template>
