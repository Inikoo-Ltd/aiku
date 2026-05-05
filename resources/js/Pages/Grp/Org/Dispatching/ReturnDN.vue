<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import DummyComponent from "@/Components/DummyComponent.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, defineAsyncComponent, ref } from 'vue'
import type { Component } from 'vue'

import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import Timeline from '@/Components/Utils/Timeline.vue'
import BoxNote from '@/Components/Pallet/BoxNote.vue'
import TableReturnDNItems from '@/Components/Warehouse/DeliveryNotes/TableReturnDNItems.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import BoxStatsDeliveryNote from '@/Components/Warehouse/DeliveryNotes/BoxStatsDeliveryNote.vue'

// import FileShowcase from '@/xxxxxxxxxxxx'

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
	timelines: {}[]
    delivery_note: {
        state: string
    }
	box_stats: {}
	returned_delivery_note_state: {
		value: string
		label: string
	}
	notes: {
		note_list: {
			label: string,
			note: string
		}[]
	}
	routes: {
		update: {
			name: string,
			parameters: Record<string, any>,
			method: string
		},
	}
	is_faire_order: boolean
	allow_waiting: boolean
	allow_picker_set_not_picked: boolean
	showChangePickerPacker: boolean
	items: {}
	pending_items?: {}
	done_items?: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components: Component = {
        items: TableReturnDNItems,
        pending_items: TableReturnDNItems,
        done_items: TableReturnDNItems,
		history: TableHistories,
    }

    return components[currentTab.value]

})

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

	<!-- Section: Box Note (TODO: update the routes ) -->
	<div
		vxif="
			pickingView ||
			delivery_note_state.value === 'dispatched' ||
			delivery_note_state.value === 'cancelled'
		"
		class="relative">
		<div
			xv-if="notes?.note_list?.some(item => !!(item?.note?.trim()))"
			class="p-2 grid grid-cols-2 sm:grid-cols-3 gap-y-2 gap-x-2 h-fit lg:max-h-64 w-full lg:justify-center border-b border-gray-300">
			<BoxNote
				v-for="(note, index) in notes.note_list"
				:key="index + note.label"
				:noteData="note"
				:updateRoute="routes.update"
				:fetchRoute="{
					name: 'grp.models.delivery_note.copy_notes',
					parameters: { deliveryNote: props.delivery_note.id },
					method: 'patch',
				}" />
		</div>
	</div>

	<!-- Section: Timeline -->
	<div v-if="timelines" class="mt-4 sm:mt-1 border-b border-gray-200 pb-2">
		<Timeline
			:options="timelines"
			:state="returned_delivery_note_state.value"
			:slidesPerView="6"
			:format-time="'MMMM d yyyy, HH:mm'" />
	</div>

	<BoxStatsDeliveryNote
		v-if="box_stats"
		:showChangePickerPacker="showChangePickerPacker"
		:boxStats="box_stats"
		:routes
		:deliveryNote="delivery_note"
		:updateRoute="routes.update"
	/>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component
		:is="component"
		:data="props[currentTab as keyof typeof props]"
		:tab="currentTab"
	/>
</template>