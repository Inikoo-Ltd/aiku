<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDotCircle, faUnlink, faExclamationTriangle, faUndo, faPlus, faSeedling, faTrash } from "@fal"
import { faDotCircle as fasDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { InputNumber, AutoComplete } from 'primevue'
import { ref, computed, inject } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { router, useForm } from '@inertiajs/vue3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { StockLocation, StockManagementRoutes } from '@/types/Inventory/StocksManagement'
import { notify } from '@kyvg/vue3-notification'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
library.add(faDotCircle, fasDotCircle, faUnlink, faExclamationTriangle, faUndo, faPlus, faSeedling, faTrash)

const props = defineProps<{
    locations: StockLocation[]
    routes: StockManagementRoutes
}>()

console.log('editlocations', props)

const emits = defineEmits<{
    (e: "onClickBackground"): void
}>()

const layout = inject('layout', layoutStructure)


const newLocations = ref<StockLocation[]>([])
const form = useForm({
    stockCheck: props.locations.map(item => ({
        id: item.id,
        code: item.code,
        quantity: Number(item.quantity),
        audited_at: item.audited_at,
        isUnlinked: false // Tambah property untuk track unlink status
    }))
})


// Function untuk handle unlink
const idLocationLoading = ref<number[]>([])
const handleUnlink = (loc: StockLocation) => {
    // Section: Submit
    router.delete(
        route(props.routes.disassociate_location_route.name, {
            locationOrgStock: loc.id
        }),
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                idLocationLoading.value.push(loc.id)
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully unlink location :xlocation", { xlocation: loc.code }),
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to unlink location"),
                    type: "error"
                })
            },
            onFinish: () => {
                idLocationLoading.value = idLocationLoading.value.filter(id => id !== loc.id)
            },
        }
    )
}

// Function untuk handle undo unlink
const handleUndo = (index: number) => {
    // Set item kembali menjadi linked
    form.stockCheck[index].isUnlinked = false
}

// Function untuk filter locations berdasarkan query
// const searchLocations = (event) => {
//     const query = event.query.toLowerCase()
//     // Filter locations yang belum ada di stockCheck, newLocations, dan belum di-unlink
//     const existingLocationIds = form.stockCheck
//         .filter(item => !item.isUnlinked)
//         .map(item => item.id)
//     const newLocationIds = newLocations.value.map(item => item.id)
//     const allUsedIds = [...existingLocationIds, ...newLocationIds]

//     filteredLocations.value = availableLocations.value.filter(location => {
//         return location.name.toLowerCase().includes(query) &&
//             !allUsedIds.includes(location.id)
//     })
// }


// Function untuk remove new location
const removeNewLocation = (index: number) => {
    newLocations.value.splice(index, 1)
}

const isLoadingAddNewLocation = ref(false)
const onAddNewLocation = () => {
    // Prepare data dengan array terpisah
    const activeItems = form.stockCheck.filter(item => !item.isUnlinked)
    const unlinkedItems = form.stockCheck.filter(item => item.isUnlinked)

    console.log("Submitting stock check data:", {
        existingLocations: activeItems,
        unlinkedLocations: unlinkedItems,
        newLocations: newLocations.value
    })
    
    // Section: Submit
    router.post(
        route(props.routes.associate_location_route.name, {
            ...props.routes.associate_location_route.parameters,
            location: newLocation.value?.id
        }),
        {
            
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingAddNewLocation.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully add new location :xlocation", { xlocation: newLocation.value.code }),
                    type: "success"
                })
                newLocation.value = null
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add new location"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingAddNewLocation.value = false
            },
        }
    )

    // form.post(route('grp.dashboard.show'), {
    //     data: {
    //         existingLocations: activeItems,
    //         unlinkedLocations: unlinkedItems,
    //         newLocations: newLocations.value
    //     },
    //     preserveScroll: true,
    //     onStart: () => {
    //         console.log("Submitting stock check...")
    //     },
    //     onSuccess: () => {
    //         console.log("Stock check submitted successfully!")
    //         emits('onClickBackground')
    //     },
    //     onError: (errors) => {
    //         console.error("Failed to submit stock check:", errors)
    //     },
    //     onFinish: () => {
    //         console.log("Stock check submission finished.")
    //     }
    // })
}

const newLocation = ref({})
// Function untuk menambah location baru
const addNewLocation = () => {
    newLocations.value.push(newLocation.value)
    newLocation.value = {}
}
</script>

<template>
    <div class="">
        <div @click="() => emits('onClickBackground')" class="cursor-pointer fixed inset-0 bg-black/40 z-30" />
        <div class="isolate relative bg-white z-30 py-2 px-3 space-y-1">
            <div class="text-center">Edit Locations</div>

            <!-- V-FOR 1: Existing locations -->
            <div v-for="(loc, idx) in props.locations" :key="'existing-' + loc.id"
                class="grid grid-cols-7 gap-x-3 items-center gap-2" :class="{ 'opacity-75': loc.isUnlinked }">

                <div class="flex items-center gap-x-2">
                    <span :class="{ 'line-through text-gray-500': loc.isUnlinked }">
                        {{ loc.code }}
                    </span>
                </div>

                <div class="col-span-3">
                    <span class="text-sm italic text-gray-400" :class="{ 'line-through': loc.isUnlinked }">
                        Current Stock {{ Number(loc.quantity) }}
                    </span>
                    <!-- Show reduced stock when unlinked -->
                    <span v-if="loc.isUnlinked" class="text-sm italic text-red-500 ml-1">
                        (-{{ loc.quantity }})
                    </span>
                </div>

                <div class="isolate col-span-3 flex justify-end items-center gap-x-2">
                    <!-- Warning icon dan text danger untuk item yang di-unlink -->
                    <div v-if="loc.isUnlinked" class="flex items-center gap-x-1 text-red-500 text-xs">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-500" />
                        <span>Will be unlinked</span>
                    </div>

                    <!-- Unlink/Undo button -->
                    <!-- <button v-if="!loc.isUnlinked" class="cursor-pointer opacity-50 hover:opacity-100"
                        v-tooltip="'Unlink Location'" @click="handleUnlink(loc)">
                        <FontAwesomeIcon icon="fal fa-unlink" />
                    </button> -->

                    <div v-if="idLocationLoading.includes(loc.id)">
                        <LoadingIcon />
                    </div>
                    <ModalConfirmationDelete
                        v-else
                        :routeDelete="{
                            name: props.routes.disassociate_location_route.name,
                            parameters: { locationOrgStock: loc.id }
                        }"
                        :title="trans('Are you sure you want to unlink location?')"
                        :description="trans('This will remove the stock in the location as well')"
                        isFullLoading
                        :noLabel="trans('Yes, unlink location :xloc', { xloc: loc.code })"
                        noIcon="fal fa-unlink"
                        class="z-50"
                    >
                        <template #default="{ isOpenModal, changeModel }">
                            <div @click="() => changeModel()" xclick="handleUnlink(loc)" class="cursor-pointer text-red-500 opacity-50 hover:opacity-100" v-tooltip="trans('Unlink Location')">
                                <FontAwesomeIcon icon="fal fa-unlink" class="" fixed-width aria-hidden="true" />
                            </div>
                        </template>
                    </ModalConfirmationDelete>
                    


                    <!-- Undo button -->
                    <!-- <button v-if="loc.isUnlinked" class="cursor-pointer hover:text-black text-green-600"
                        v-tooltip="'Undo Unlink'" @click="handleUndo(idx)">
                        <FontAwesomeIcon icon="fal fa-undo" />
                    </button> -->
                </div>
            </div>

            <!-- V-FOR 2: New locations (belum ter-link) -->
            <div v-for="(newLoc, idx) in newLocations" :key="'new-' + newLoc.id"
                class="grid grid-cols-7 gap-x-3 items-center gap-2">

                <div class="flex items-center gap-x-2 col-span-2">
                    <span>{{ newLoc.code }}</span>
                </div>

                <div class="">
                    <!-- No current stock display for new locations -->
                </div>

                <div class="col-span-4 flex justify-end items-center gap-x-2">
                    <!-- Seedling icon for new location -->
                    <div class="flex items-center gap-x-1 text-green-600 text-sm" v-tooltip="'Location to be linked'">
                        <FontAwesomeIcon icon="fal fa-seedling" />
                    </div>

                    <!-- Trash button untuk remove -->
                    <button class="cursor-pointer hover:text-black text-red-400" v-tooltip="'Remove Location'"
                        @click="removeNewLocation(idx)">
                        <FontAwesomeIcon icon="fal fa-trash" />
                    </button>
                </div>
            </div>

            <!-- Add new location section -->
            <div class="border-t border-gray-200 pt-3 mt-3">
                <div class="text-sm font-medium text-gray-600 mb-2">Add New Location</div>
                <div class="flex gap-x-2 items-center">
                    <div class="flex-1">
                        <PureMultiselectInfiniteScroll
                            v-model="newLocation"
                            :fetchRoute="routes.location_route"
                            object
                        />
                        <!-- <AutoComplete v-model="selectedLocation" :suggestions="filteredLocations"
                            @complete="searchLocations" optionLabel="name" placeholder="Search and select location..."
                            class="w-full" inputClass="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                            panelClass="bg-white border border-gray-300 rounded-md shadow-lg" /> -->
                    </div>

                    <Button
                        @click="() => onAddNewLocation()"
                        :disabled="!newLocation"
                        :loading="isLoadingAddNewLocation"
                        xlabel="trans('Add location')"
                        icon="fal fa-plus"
                    />
                    <!-- <button @click="() => onAddNewLocation()" :disabled="!newLocation"
                        class="px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed"
                        title="Add Location">
                        <FontAwesomeIcon icon="fal fa-plus" />
                    </button> -->
                </div>
            </div>
            <!-- <pre>{{ locations }}</pre> -->
        </div>

        <!-- Section: buttons -->
        <div class="relative flex gap-x-2 isolate z-30 mt-4">
            <Button label="Cancel" type="cancel" key="2" class="bg-red-100" @click="() => emits('onClickBackground')" />

            <Button
                v-if="layout.app.environment === 'local'"
                :disabled="!form.isDirty"
                label="Save"
                full
                aclick="() => onAddNewLocation()"
            />
        </div>

        <pre v-if="layout.app.environment === 'local'">{{ { form: form, newLocations: newLocations } }}</pre>
    </div>
</template>