<!--
* Author: Vika Aqordi
* Created on: 18-02-2025-09h-16m
* Github: https://github.com/aqordeon
* Copyright: 2025
-->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
// import TableCustomerClients from "@/Components/Tables/Grp/Org/CRM/TableCustomerClients.vue"
import { capitalize } from "@/Composables/capitalize"
// import Button from "@/Components/Elements/Buttons/Button.vue"
// import UploadExcel from "@/Components/Upload/UploadExcel.vue"
// import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
// import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
// import { useTabChange } from "@/Composables/tab-change"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHeart } from "@fas"
import { faBoxOpen, faImage } from "@fal"
import Table from "@/Components/Table/Table.vue"
import Image from "@/Components/Image.vue"
// import CopyButton from "@/Components/Utils/CopyButton.vue"
import { GridProducts } from "@/Components/Product"
library.add(faHeart, faBoxOpen, faImage)
const props = defineProps<{
	data: {}
	// tabs: {
	//     current: string
	//     navigation: {}
	// },
	title: string
	pageHead: {}
	// active?: {}
	// inactive?: {}
	// upload_spreadsheet : object
}>()

const isModalUploadOpen = ref(false)

// const currentTab = ref<string>(props.tabs.current)
// const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

// const component = computed(() => {
//     const components: any = {
//         active: TableCustomerClients,
//         inactive: TableCustomerClients
//     }

//     return components[currentTab.value]
// })
</script>

<template>

	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">

	</PageHeading>
	<!-- <TableCustomerClients :data="data" /> -->
	<!-- <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" /> -->
	<!-- <pre>{{ data }}</pre> -->
	<!-- <Table :resource="data" xname="tab" class="mt-5">
		<template #cell(code)="{ item }">
			<div>
				<a :href="item.url" class="primaryLink">
					{{ item.code }}
				</a>

				<CopyButton :text="item.code" class="ml-1" />
			</div>
		</template>

		<template #cell(actions)="{ item }">
			<div class="w-10 h-10">
				<Image :src="item.image?.thumbnail" imageCover />
			</div>
		</template>
	</Table> -->
	
	<!-- Pass the data to GridProducts component -->
	<GridProducts 
		:resource="data" 
		:preserve-scroll="true"
		class="mt-5"
	/>

</template>
