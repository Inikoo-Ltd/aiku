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
import { stockLocation } from '@/types/StockLocation'
library.add(faDotCircle, fasDotCircle, faUnlink, faExclamationTriangle, faUndo, faPlus, faSeedling, faTrash)

const props = defineProps<{
    locations: StockLocation[]
    routes: StockManagementRoutes
}>()

console.log('editlocations', props)

const emits = defineEmits<{
    (e: "onClickBackground"): void
}>()


const isLoadingAddNewLocation = ref(false)
const onAddNewLocation = () => {
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
                    text: trans("Successfully add new location :xlocation", { xlocation: newLocation.value?.code || 'no_code' }),
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
}

const newLocation = ref<stockLocation | null>(null)
</script>

<template>
    <div class="-mt-9 ">
        <div @click="() => emits('onClickBackground')" class="cursor-pointer fixed inset-0 bg-black/40 z-30" />
        <div class="isolate relative bg-white z-30 py-2 px-3">
            <div class="text-center font-bold text-xl mb-4">{{ trans("Edit Locations") }}</div>

            <!-- V-FOR 1: Existing locations -->
            <div class="flex flex-col gap-y-3">
                <div v-for="(loc, idx) in props.locations" :key="'existing-' + loc.id"
                    class="grid grid-cols-7 gap-x-3 items-center gap-2">
                    <div class="flex items-center gap-x-2">
                        {{ loc.code }}
                    </div>
                    <div class="col-span-3">
                        <span class="text-sm italic text-gray-400">
                            {{ trans("Current Stock") }} {{ Number(loc.quantity) }}
                        </span>
                    </div>
                    <div class="isolate col-span-3 flex justify-end items-center gap-x-2">
                        <ModalConfirmationDelete
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
                            <template #default="{ isOpenModal, changeModel, isLoadingdelete }">
                                <div @click="() => changeModel()" xclick="handleUnlink(loc)" class="cursor-pointer text-red-500 opacity-50 hover:opacity-100" v-tooltip="trans('Unlink Location')">
                                    <LoadingIcon v-if="isLoadingdelete" />
                                    <FontAwesomeIcon v-else icon="fal fa-unlink" class="" fixed-width aria-hidden="true" />
                                </div>
                            </template>
                        </ModalConfirmationDelete>
                    </div>
                </div>
            </div>

            <!-- Add new location section -->
            <div class="border-t border-gray-200 pt-3 mt-3">
                <div class="text-sm font-medium text-gray-600 mb-2">{{ trans("Add New Location") }}</div>
                <div class="flex gap-x-2 items-center">
                    <div class="flex-1">
                        <PureMultiselectInfiniteScroll
                            v-model="newLocation"
                            :fetchRoute="routes.location_route"
                            object
                            labelProp="code"
                        />
                    </div>

                    <Button
                        @click="() => onAddNewLocation()"
                        :disabled="!newLocation"
                        :loading="isLoadingAddNewLocation"
                        :label="trans('Add')"
                        icon="fal fa-plus"
                    />
                </div>
            </div>
        </div>

        <!-- Section: buttons -->
        <div class="relative flex gap-x-2 isolate z-30 mt-4">
            <Button :label="trans('Cancel')" type="cancel" key="2" class="bg-red-100" @click="() => emits('onClickBackground')" />
        </div>

    </div>
</template>