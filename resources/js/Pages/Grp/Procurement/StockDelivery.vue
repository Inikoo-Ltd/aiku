<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 16:07:20 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import type { Component } from "vue"
import { Head } from "@inertiajs/vue3"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Timeline from "@/Components/Utils/Timeline.vue"
import ProcurementOrderData from "@/Components/Procurement/ProcurementOrderData.vue"
import TableStockDeliveryItems from "@/Components/Tables/Grp/Org/Procurement/TableStockDeliveryItems.vue"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"

import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { Timeline as TSTimeline } from "@/types/Timeline"

import { library } from "@fortawesome/fontawesome-svg-core"
import { faInventory, faWarehouse, faPersonDolly, faBoxUsd, faTruck, faTerminal, faCameraRetro, faPaperclip, faInfoCircle } from "@fal"

library.add(
	faInventory,
	faWarehouse,
	faPersonDolly,
	faBoxUsd,
	faTruck,
	faTerminal,
	faCameraRetro,
	faPaperclip,
	faInfoCircle
)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	stock_delivery: {
		state: string
	}
	timelines: {
		[key: string]: TSTimeline
	}
	tabs: {
		current: string
		navigation: {}
	}
	attachmentRoutes: {
		attachRoute: routeType
		detachRoute: routeType
	}
	showcase?: {}
	items?: {}
	attachments?: {}
	history?: {}
}>()

const currentTab = ref(props.tabs.current)
const isModalUploadOpen = ref(false)

const component = computed(() => {
	const components: Component = {
		showcase: ProcurementOrderData,
		items: TableStockDeliveryItems,
		attachments: TableAttachments,
		history: TableHistories,
	}

	return components[currentTab.value]
})

const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<Button
				v-if="currentTab === 'attachments'"
				label="Attach"
				icon="upload"
				@click="() => (isModalUploadOpen = true)"
			/>
		</template>
	</PageHeading>

	<!-- Stock Delivery Timeline -->
	<div v-if="timelines" class="py-2 border-b border-gray-300">
		<Timeline
			:options="timelines"
			:state="stock_delivery.state"
			:slidesPerView="6"
			:format-time="'MMMM d yyyy, HH:mm'"
		/>
	</div>

	<Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
	<component
		:is="component"
		:data="props[currentTab]"
		:tab="currentTab"
		:detachRoute="attachmentRoutes.detachRoute"
	/>

	<UploadAttachment
		v-model="isModalUploadOpen"
		scope="attachment"
		:title="{
			label: 'Upload your file',
			information: 'The list of column file: customer_reference, notes, stored_items',
		}"
		progressDescription="Adding Stock Delivery Attachments"
		:attachmentRoutes="attachmentRoutes"
	/>
</template>
