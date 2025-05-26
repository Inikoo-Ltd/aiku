<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faChair, faHandPaper, faFolder, faBoxCheck } from '@fal'
import { faArrowRight, faCheck } from '@fas'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import AlertMessage from '@/Components/Utils/AlertMessage.vue'
import BoxNote from '@/Components/Pallet/BoxNote.vue'
import Timeline from '@/Components/Utils/Timeline.vue'
import { Timeline as TSTimeline } from '@/types/Timeline'
import { computed, ref } from 'vue'
import type { Component } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import BoxStatsDeliveryNote from '@/Components/Warehouse/DeliveryNotes/BoxStatsDeliveryNote.vue'
import TableSKOSOrdered from '@/Components/Warehouse/DeliveryNotes/TableSKOSOrdered.vue'
import TablePickings from '@/Components/Warehouse/DeliveryNotes/TablePickings.vue'
import { routeType } from '@/types/route'
import Tabs from '@/Components/Navigation/Tabs.vue'
import type { DeliveryNote } from '@/types/warehouse'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { trans } from 'laravel-vue-i18n'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { notify } from '@kyvg/vue3-notification'


library.add(faFolder, faBoxCheck, faCube, faChair, faHandPaper, faArrowRight, faCheck)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    items?: {}
    pickings?: {}
    alert?: {
        status: string
        title?: string
        description?: string
    }
    delivery_note: DeliveryNote
    notes?: {
        note_list: {
            label: string
            note: string
            editable?: boolean
            bgColor?: string
            textColor?: string
            color?: string
            lockMessage?: string
            field: string  // customer_notes, public_notes, internal_notes
        }[]
        // updateRoute: routeType
    }
    timelines: {
        [key: string]: TSTimeline
    }
    box_stats: {}
    routes: {
        update: routeType
        products_list: routeType
        pickers_list: routeType
        packers_list: routeType
        set_queue: routeType
    }
    delivery_note_state: {
        label: string
        value: string
    }
}>()

const currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const component = computed(() => {
    const components: Component = {
        items: TableSKOSOrdered,
        pickings: TablePickings,
    }

    return components[currentTab.value]
})

// Section: To Queue
const isModalToQueue = ref(false)

// Section: Picker
const selectedPicker = ref(props.box_stats.picker)
const disable = ref(props.box_stats.state)
const isLoading = ref<{ [key: string]: boolean }>({})
const isLoadingToQueue = ref(false)
const onSetToQueue = () => {
    router.patch(
        route(props.routes.set_queue.name, {
            ...props.routes.set_queue.parameters,
            user: selectedPicker.value.id,
        }),
        {
            
        },
        {
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: error.message,
                    type: "error",
                })
            },
            onSuccess: () => {
                isModalToQueue.value = false
            },
            onStart: () => isLoadingToQueue.value = true,
            onFinish: () => isLoadingToQueue.value = false,
            preserveScroll: true,
        }
    )
}
const onUpdatePicker = () => {
    router.patch(
        route(props.routes.update.name, props.routes.update.parameters),
        {
            picker_user_id: selectedPicker.value.id
        },
        {
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: error.message,
                    type: "error",
                })
            },
            onSuccess: () => {
                isModalToQueue.value = false
            },
            onStart: () => isLoadingToQueue.value = true,
            onFinish: () => isLoadingToQueue.value = false,
            preserveScroll: true,
        }
    )
}
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-to-queue ="{ action }">
            <Button
                @click="isModalToQueue = true"
                :label="action.label"
                :icon="action.icon"
                :iconRight="action.iconRight"
                :type="action.type"
            />
        </template>
        <template #button-change-picker ="{ action }">
            <Button
                @click="isModalToQueue = true"
                :label="action.label"
                :icon="action.icon"
                type="tertiary"
            />
        </template>
    </PageHeading>

    <!-- Section: Pallet Warning -->
    <div v-if="alert?.status" class="p-2 pb-0">
        <AlertMessage :alert />
    </div>
    
    <!-- Section: Box Note -->
    <div class="relative">
        <Transition name="headlessui">
            <div v-if="notes?.note_list?.some(item => !!(item?.note?.trim()))" class="p-2 grid sm:grid-cols-3 gap-y-2 gap-x-2 h-fit lg:max-h-64 w-full lg:justify-center border-b border-gray-300">
                <BoxNote
                    v-for="(note, index) in notes.note_list"
                    :key="index+note.label"
                    :noteData="note"
                    :updateRoute="routes.update"
                />
            </div>
        </Transition>
    </div>

    <!-- Section: Timeline -->
    <div v-if="timelines" class="mt-4 sm:mt-1 border-b border-gray-200 pb-2">
        <Timeline
            :options="timelines"
            :state="delivery_note.state"
            :slidesPerView="6"
        />
    </div>

    <BoxStatsDeliveryNote v-if="box_stats" :boxStats="box_stats" :routes/>

    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

    <div class="pb-12">
        <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" :routes :state="delivery_note.state" />
    </div>
    <Modal
        :isOpen="isModalToQueue"
        @close="isModalToQueue = false"
        width="w-full max-w-lg"
        :title
    >
        <div class="mt-1 flex flex-col items-start w-full pr-3 gap-y-1.5">
            <div class="mx-auto font-semibold text-lg">
                {{ trans("Select Picker") }}
            </div>

            <div class="mt-4 flex items-center w-full gap-x-1.5">
                <dd class="flex-1">
                    <!-- Label for Picker -->
                    <div class="text-sm font-medium">
                        {{ trans("Select picker") }}
                    </div>
                    <PureMultiselectInfiniteScroll
                        v-model="selectedPicker"
                        xxxupdate:modelValue="
                            (selectedPicker) => onSubmitPickerPacker(selectedPicker, 'picker')
                        "
                        required
                        :fetchRoute="routes.pickers_list"
                        :placeholder="trans('Select picker')"
                        labelProp="contact_name"
                        valueProp="id"
                        object
                        clearOnBlur
                        :loading="isLoading['picker' + selectedPicker?.id]"
                        :disabled="disable == 'picker_assigned' || disable == 'packing' || disable == 'packed' || disable == 'finalised' || disable == 'settled'"
                        >
                        <template #singlelabel="{ value }">
                            <div
                                class="w-full text-left pl-3 pr-2 text-sm whitespace-nowrap truncate">
                                {{ value.contact_name }}
                            </div>
                        </template>

                        <template #option="{ option, isSelected, isPointed }">
                            <div class="w-full text-left text-sm whitespace-nowrap truncate">
                                {{ option.contact_name }}
                            </div>
                        </template>
                    </PureMultiselectInfiniteScroll>
                </dd>
            </div>

            <div class="w-full mt-2">
                <Button
                    @click="delivery_note_state.value === 'queued' ? onUpdatePicker() : onSetToQueue()"
                    :label="delivery_note_state.value === 'queued' ? trans('Change picker') : trans('Set Picker')"
                    :iconRight="['fas', 'fa-arrow-right']"
                    full
                    :loading="isLoadingToQueue"
                    :disabled="!selectedPicker"
                    v-tooltip="selectedPicker ? '' : trans('Select picker before set to queue')"
                >

                </Button>
            </div>
        </div>

			<!-- <div
				v-tooltip="trans('Select Packer')"
				class="mt-1 flex flex-col items-start w-full pr-3 gap-y-1.5">
				<div class="flex items-center w-full gap-x-1.5">
					<dt class="flex-none">
						<FontAwesomeIcon
							icon="fal fa-weight"
							fixed-width
							aria-hidden="true"
							class="text-gray-500" />
					</dt>
					<dd class="flex-1">
						<label class="text-sm font-medium text-gray-700">
							{{ trans("Packer") }}
						</label>
						<PureMultiselectInfiniteScroll
							v-model="selectedPacker"
							@update:modelValue="
								(selectedPacker) => onSubmitPickerPacker(selectedPacker, 'packer')
							"
							:fetchRoute="routes.packers_list"
							:placeholder="trans('Select packer')"
							labelProp="contact_name"
							valueProp="id"
							object
							clearOnBlur
							:loading="isLoading['packer' + selectedPacker?.id]"
							:disabled="disable == 'packing' ||  disable == 'packed' || disable == 'finalised' || disable == 'settled'">
							<template #singlelabel="{ value }">
								<div
									class="w-full text-left pl-3 pr-2 text-sm whitespace-nowrap truncate">
									{{ value.contact_name }}
								</div>
							</template>

							<template #option="{ option, isSelected, isPointed }">
								<div class="w-full text-left text-sm whitespace-nowrap truncate">
									{{ option.contact_name }}
								</div>
							</template>
						</PureMultiselectInfiniteScroll>
					</dd>
				</div>
			</div> -->
    </Modal>
</template>
