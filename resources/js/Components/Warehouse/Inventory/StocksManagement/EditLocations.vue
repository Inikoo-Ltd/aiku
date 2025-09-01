<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDotCircle, faUnlink, faExclamationTriangle, faUndo, faPlus, faSeedling, faTrash } from "@fal"
import { faDotCircle as fasDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { InputNumber, AutoComplete } from 'primevue'
import { ref, computed } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useForm } from '@inertiajs/vue3'
library.add(faDotCircle, fasDotCircle, faUnlink, faExclamationTriangle, faUndo, faPlus, faSeedling, faTrash)

const props = defineProps<{
    part_locations: {
        id: number
        name: string
        slug: string
        stock: number
        isAudited: boolean
    }[]
}>()

const emits = defineEmits<{
    (e: "onClickBackground"): void
}>()

// Dummy data untuk autocomplete
const availableLocations = ref([
    { id: 101, name: 'Warehouse A', slug: 'warehouse-a', stock: 0, isAudited: false },
    { id: 102, name: 'Warehouse B', slug: 'warehouse-b', stock: 0, isAudited: false },
    { id: 103, name: 'Storage Room C', slug: 'storage-room-c', stock: 0, isAudited: false },
    { id: 104, name: 'Distribution Center D', slug: 'distribution-center-d', stock: 0, isAudited: false },
    { id: 105, name: 'Logistics Hub E', slug: 'logistics-hub-e', stock: 0, isAudited: false },
])

// State untuk autocomplete
const selectedLocation = ref(null)
const filteredLocations = ref([])

// State untuk new locations (yang belum ter-link)
const newLocations = ref([])

const form = useForm({
    stockCheck: props.part_locations.map(item => ({
        id: item.id,
        name: item.name,
        stock: item.stock,
        isAudited: item.isAudited,
        isUnlinked: false // Tambah property untuk track unlink status
    }))
})

// Computed untuk hitung berapa location yang masih aktif (tidak di-unlink)
const activeLocationsCount = computed(() => {
    return form.stockCheck.filter(item => !item.isUnlinked).length
})

// Function untuk handle unlink
const handleUnlink = (index: number) => {
    // Set item menjadi unlinked
    form.stockCheck[index].isUnlinked = true
}

// Function untuk handle undo unlink
const handleUndo = (index: number) => {
    // Set item kembali menjadi linked
    form.stockCheck[index].isUnlinked = false
}

// Function untuk filter locations berdasarkan query
const searchLocations = (event) => {
    const query = event.query.toLowerCase()
    // Filter locations yang belum ada di stockCheck, newLocations, dan belum di-unlink
    const existingLocationIds = form.stockCheck
        .filter(item => !item.isUnlinked)
        .map(item => item.id)
    const newLocationIds = newLocations.value.map(item => item.id)
    const allUsedIds = [...existingLocationIds, ...newLocationIds]

    filteredLocations.value = availableLocations.value.filter(location => {
        return location.name.toLowerCase().includes(query) &&
            !allUsedIds.includes(location.id)
    })
}

// Function untuk menambah location baru
const addNewLocation = () => {
    if (!selectedLocation.value) return

    // Check duplicate (double safety)
    const existingIds = form.stockCheck.map(item => item.id)
    const newIds = newLocations.value.map(item => item.id)
    const allIds = [...existingIds, ...newIds]

    if (allIds.includes(selectedLocation.value.id)) {
        alert('This location is already added!')
        return
    }

    // Add location ke newLocations array
    newLocations.value.push({
        id: selectedLocation.value.id,
        name: selectedLocation.value.name,
        slug: selectedLocation.value.slug,
        stock: 0, // Default stock 0
        isAudited: false
    })

    // Clear input field
    selectedLocation.value = null
}

// Function untuk remove new location
const removeNewLocation = (index: number) => {
    newLocations.value.splice(index, 1)
}

const submitCheckStock = () => {
    // Prepare data dengan array terpisah
    const activeItems = form.stockCheck.filter(item => !item.isUnlinked)
    const unlinkedItems = form.stockCheck.filter(item => item.isUnlinked)

    console.log("Submitting stock check data:", {
        existingLocations: activeItems,
        unlinkedLocations: unlinkedItems,
        newLocations: newLocations.value
    })

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
</script>

<template>
    <div>
        <div @click="() => emits('onClickBackground')" class="cursor-pointer fixed inset-0 bg-black/40 z-30" />
        <div class="relative bg-white z-40 py-2 px-3 space-y-1">
            <div class="text-center">Edit Locations</div>

            <!-- V-FOR 1: Existing locations -->
            <div v-for="(forrrmm, idx) in form.stockCheck" :key="'existing-' + forrrmm.id"
                class="grid grid-cols-7 gap-x-3 items-center gap-2" :class="{ 'opacity-75': forrrmm.isUnlinked }">

                <div class="flex items-center gap-x-2">
                    <span :class="{ 'line-through text-gray-500': forrrmm.isUnlinked }">
                        {{ forrrmm.name }}
                    </span>
                </div>

                <div class="col-span-3">
                    <span class="text-sm italic text-gray-400" :class="{ 'line-through': forrrmm.isUnlinked }">
                        Current Stock {{ forrrmm.stock }}
                    </span>
                    <!-- Show reduced stock when unlinked -->
                    <span v-if="forrrmm.isUnlinked" class="text-sm italic text-red-500 ml-1">
                        (-{{ forrrmm.stock }})
                    </span>
                </div>

                <div class="col-span-3 flex justify-end items-center gap-x-2">
                    <!-- Warning icon dan text danger untuk item yang di-unlink -->
                    <div v-if="forrrmm.isUnlinked" class="flex items-center gap-x-1 text-red-500 text-xs">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-500" />
                        <span>Will be unlinked</span>
                    </div>

                    <!-- Unlink/Undo button -->
                    <button v-if="!forrrmm.isUnlinked" class="cursor-pointer hover:text-black text-gray-400"
                        v-tooltip="'Unlink Location'" @click="handleUnlink(idx)">
                        <FontAwesomeIcon icon="fal fa-unlink" />
                    </button>

                    <!-- Undo button -->
                    <button v-if="forrrmm.isUnlinked" class="cursor-pointer hover:text-black text-green-600"
                        v-tooltip="'Undo Unlink'" @click="handleUndo(idx)">
                        <FontAwesomeIcon icon="fal fa-undo" />
                    </button>
                </div>
            </div>

            <!-- V-FOR 2: New locations (belum ter-link) -->
            <div v-for="(newLoc, idx) in newLocations" :key="'new-' + newLoc.id"
                class="grid grid-cols-7 gap-x-3 items-center gap-2">

                <div class="flex items-center gap-x-2 col-span-2">
                    <span>{{ newLoc.name }}</span>
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
                        <AutoComplete v-model="selectedLocation" :suggestions="filteredLocations"
                            @complete="searchLocations" optionLabel="name" placeholder="Search and select location..."
                            class="w-full" inputClass="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                            panelClass="bg-white border border-gray-300 rounded-md shadow-lg" />
                    </div>
                    <button @click="addNewLocation" :disabled="!selectedLocation"
                        class="px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed"
                        title="Add Location">
                        <FontAwesomeIcon icon="fal fa-plus" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Section: buttons -->
        <div class="relative flex gap-x-2 z-40 mt-4">
            <Button label="Cancel" type="cancel" key="2" class="bg-red-100" @click="() => emits('onClickBackground')" />

            <Button :disabled="!form.isDirty" label="Save" full @click="() => submitCheckStock()" />
        </div>

        <pre>{{ { form: form, newLocations: newLocations } }}</pre>
    </div>
</template>