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
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import { library } from "@fortawesome/fontawesome-svg-core"
import {faUsersSlash } from "@fal"
library.add(faUsersSlash)
const props = defineProps<{
	data: {}
	tabs: {
        current: string
        navigation: {}
    },
	title: string
	pageHead: {}
	active?: {}
    inactive?: {}
    upload_spreadsheet : object
}>()

const isModalUploadOpen = ref(false)

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: any = {
        active: TableCustomerClients,
        inactive: TableCustomerClients
    }

    return components[currentTab.value]
})
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
	<!-- <TableCustomerClients :data="data" /> -->
 	<Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
	<component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
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
