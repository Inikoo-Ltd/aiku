<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
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
import BoxStatsInDNReturn from '@/Components/Warehouse/DeliveryNotes/BoxStatsInDNReturn.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { notify } from '@kyvg/vue3-notification'
import { cloneDeep, set } from 'lodash'

// import FileShowcase from '@/xxxxxxxxxxxx'

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
	timelines: {}[]
    delivery_note: {
        state: string
    }
	box_stats: {
		picker: {
			id: number,
			contact_name: string
		}
	}
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
	warehouse: {
		slug: string
	}
	organisation: {
		slug: string
	}
	// is_faire_order: boolean
	// allow_waiting: boolean
	// allow_picker_set_not_picked: boolean
	is_editable: boolean
	quick_pickers: {
		id: number,
		contact_name: string
	}[]
	showChangePickerPacker: boolean
	dn_return: {
		id: number
		slug: string
		reference: string
		date: string
		state: string
		updated_at: string
	}
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

const isModalToQueue = ref(false)

// Section: Picker
const selectedPicker = ref(props.box_stats.picker)
const isLoading = ref<{ [key: string]: boolean }>({})
const isLoadingToQueue = ref(false)
const onUpdateHandler = () => {

    router.patch(
        route('grp.models.return_delivery_note.update', {
			returnDeliveryNote: props.dn_return.id
		}),
        {
			handler_user_id: selectedPicker.value.id,
		},
        {
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: error.message,
                    type: "error"
                });
            },
            onSuccess: () => {
                isModalToQueue.value = false;
            },
            onStart: () => isLoadingToQueue.value = true,
            onFinish: () => isLoadingToQueue.value = false,
            preserveScroll: true
        }
    );
};

const pickingView = ref(true);

const refundedData = ref(props.items?.data ? cloneDeep(props.items?.data)
	.filter((item) => item.to_refund.quantity > 0)
	.reduce((acc, item) => {
      acc[item.to_refund.original_transaction_id] = item.to_refund;
      return acc;
    }, {}) : {});


const storedPickingView = localStorage.getItem('return-delivery-note:pickingView');
if (storedPickingView !== null) {
    pickingView.value = storedPickingView === 'true';
}

// ✅ Watch and persist to localStorage
watch(pickingView, (val) => {
    localStorage.setItem('return-delivery-note:pickingView', String(val));
})

library.add(
	faUserSlash,
	faExchangeAlt,
	faBoxCheck
)

const setRefund = async (data: {}, fieldName: string) => {
	console.log(fieldName, refundedData.value, data);
	set(refundedData.value, fieldName, data)
}

const finishProcessingRefund = (routeData) => {
	router.patch(
		route(routeData.name, routeData.parameters), 
		{
			refundedData: refundedData.value
		},
		{
			onSuccess: () => {
				notify({
					title: "Success",
					text: "Successfully processed return with refunds",
					type: "success",
				})
			},
			onError: (error) => {
				notify({
					title: "Something went wrong",
					text: error.message,
					type: "error",
				})
			},
			onFinish: () => {
				
			}
		}
	);
}

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" isButtonGroupWithBorder>
		<template #otherBefore>
			<div v-if="delivery_note.state == 'returning'" class="flex items-center gap-3 bg-gray-50 border border-gray-200 px-4 py-2 rounded-md">
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

		<template #other>

		</template>
		
		<template #button-group-change-handler="{ action }">
			<Button
				@click="isModalToQueue = true"
				v-tooltip="ctrans('Change handler to another person')"
				:label="ctrans('Change Handler')"
				icon="fal fa-exchange-alt"
				type="tertiary"
				class="border-transparent rounded-l-none" />
		</template>

		<template #button-finish-processing="{ action }">
			<Button
				@click="finishProcessingRefund(action.route)"
				:label="action.label"
				:icon="action.icon"
				:type="action.type"
				:style="action.style"
			/>
		</template>

	</PageHeading>

	<!-- Section: Box Note (TODO: update the routes ) -->
	<!-- <div v-if="delivery_note.state === 'returned'" class="relative">
		<div class="p-2 grid grid-cols-2 sm:grid-cols-3 gap-y-2 gap-x-2 h-fit lg:max-h-64 w-full lg:justify-center border-b border-gray-300">
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
	</div> -->

	<!-- Section: Timeline -->
	<div v-if="timelines" class="mt-4 sm:mt-1 border-b border-gray-200 pb-2">
		<Timeline
			:options="timelines"
			:state="returned_delivery_note_state.value"
			:slidesPerView="6"
			:format-time="'MMMM d yyyy, HH:mm'"
		/>
	</div>

	<BoxStatsInDNReturn
		v-if="box_stats"
		:showChangePickerPacker="showChangePickerPacker"
		:boxStats="box_stats"
		:routes
		:deliveryNote="delivery_note"
		:updateRoute="routes?.update"
		:warehouse
	/>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component
		:is="component"
		:data="props[currentTab as keyof typeof props]"
		:tab="currentTab"
		:is_editable="is_editable"
		@onChangeRefund="setRefund"
	/>

	<!-- Modal: Select picker -->
	<Modal :isOpen="isModalToQueue" @close="isModalToQueue = false" width="w-full max-w-lg" :title="trans('Select Picker')">
		<div class="mt-1 flex flex-col items-start w-full pr-3 gap-y-1.5">
			<div class="mx-auto font-semibold text-lg">
				{{ ctrans("Select Handler") }}
			</div>
			<div class="mt-4 flex items-center w-full gap-x-1.5">
				<dd class="flex-1">
					<!-- Label for Handler -->
					<div class="text-sm font-medium">
						{{ ctrans("Select Handler") }}
					</div>

					<PureMultiselectInfiniteScroll
						v-model="selectedPicker"
						xxxupdate:modelValue="
                            (selectedPicker) => onSubmitPickerPacker(selectedPicker, 'picker')
                        "
						required
						:fetchRoute="{
							name: 'grp.json.employees.picker_users',
							parameters: { organisation: organisation.slug },
						}"
						:placeholder="ctrans('Select handler')"
						labelProp="contact_name"
						valueProp="id"
						object
						clearOnBlur
						:loading="isLoading['picker' + selectedPicker?.id]"
						:disabled="isLoadingToQueue"
					>
						<template #singlelabel="{ value }">
							<div class="w-full text-left pl-3 pr-2 text-sm whitespace-nowrap truncate">
								{{ value.contact_name }}
							</div>
						</template>
						<template #option="{ option, isSelected, isPointed }">
							<div class="w-full text-left text-sm whitespace-nowrap truncate">
								{{ option.contact_name }}
							</div>
						</template>
					</PureMultiselectInfiniteScroll>

					<!-- Quick Pickers -->
					<div v-if="quick_pickers && quick_pickers.length > 0" class="border-y border-dashed border-gray-300 py-3 mt-3 flex flex-wrap gap-2">
						<div
							v-for="picker in quick_pickers"
							@click="(selectedPicker = picker, onUpdateHandler())"
							class="flex-grow text-center px-3 py-1.5 select-none text-sm rounded-md border border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
							:class="
								selectedPicker?.id === picker.id ? 'bg-blue-500 text-white' : 'bg-blue-50 hover:bg-blue-200 text-blue-800'
							"
							:label="picker.contact_name"
							type="tertiary"
						>
							{{ picker.contact_name }}
						</div>
						<!-- <div class="flex flex-wrap justify-center gap-2">
						</div> -->
					</div>
				</dd>
			</div>

			<div class="w-full mt-4">
				<Button
					@click="onUpdateHandler()"
					:label="ctrans('Select handler')"
					:iconRight="['fas', 'fa-arrow-right']"
					full
					:loading="isLoadingToQueue"
					:disabled="!selectedPicker"
					v-tooltip="selectedPicker ? '' : trans('Select handler before submit')">
				</Button>
			</div>
		</div>
	</Modal>
</template>
<style scoped>
.p-toggleswitch {
	--p-toggleswitch-checked-background: #10b981;
	--p-toggleswitch-checked-hover-background: #059669;
}
</style>