<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import DummyComponent from "@/Components/DummyComponent.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, defineAsyncComponent, ref, watch } from 'vue'
import type { Component } from 'vue'

import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import Timeline from '@/Components/Utils/Timeline.vue'
import BoxNote from '@/Components/Pallet/BoxNote.vue'
import TableReturnDNItems from '@/Components/Warehouse/DeliveryNotes/TableReturnDNItems.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import BoxStatsDeliveryNote from '@/Components/Warehouse/DeliveryNotes/BoxStatsDeliveryNote.vue'
import { faBoxOpen, faCheck, faExchangeAlt, faTimes, faUserSlash } from '@fal'
import { trans } from 'laravel-vue-i18n'
import { ToggleSwitch } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBoxCheck } from '@fas'

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

const isModalToQueue = ref(false);

const pickingView = ref(true);

const storedPickingView = localStorage.getItem('return-delivery-note:pickingView');
if (storedPickingView !== null) {
    pickingView.value = storedPickingView === 'true';
}

// ✅ Watch and persist to localStorage
watch(pickingView, (val) => {
    localStorage.setItem('return-delivery-note:pickingView', String(val));
});

library.add(
	faUserSlash,
	faExchangeAlt,
	faBoxCheck
)

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" isButtonGroupWithBorder>
		<template #otherBefore>
			<div
				v-if="delivery_note.state == 'returning'"
				class="flex items-center gap-3 bg-gray-50 border border-gray-200 px-4 py-2 rounded-md">
				<FontAwesomeIcon :icon="faBoxOpen" class="text-gray-400" fixed-width />
				<div class="flex items-center justify-between w-full">
					<span class="text-sm text-gray-700 font-medium mx-2">
						{{ trans("Worker View") }}
					</span>
					<ToggleSwitch v-model="pickingView">
						<template #handle="{ checked }">
							<FontAwesomeIcon
								:icon="checked ? faCheck : faTimes"
								:class="checked ? '' : 'text-red-500'"
								class="text-xs"
								fixed-width />
						</template>
					</ToggleSwitch>
				</div>
			</div>
		</template>
		
		<template #button-group-change-picker="{ action }">
			<Button
				@click="isModalToQueue = true"
				:label="action.label"
				:icon="action.icon"
				type="tertiary"
				class="border-transparent rounded-l-none" />
		</template>

	</PageHeading>

	<!-- Section: Box Note (TODO: update the routes ) -->
	<div
		v-if="
			pickingView ||
			delivery_note.state === 'returned' ||
			delivery_note.state === 'cancelled'
		"
		class="relative">
		<div
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
		v-if="box_stats && pickingView"
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
<style scoped>
.p-toggleswitch {
	--p-toggleswitch-checked-background: #10b981;
	--p-toggleswitch-checked-hover-background: #059669;
}
</style>