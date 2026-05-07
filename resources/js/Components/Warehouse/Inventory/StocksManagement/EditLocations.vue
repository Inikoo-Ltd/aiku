<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDotCircle, faUnlink, faExclamationTriangle, faUndo, faPlus, faSeedling, faTrash } from "@fal"
import { faBan, faDotCircle as fasDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { router } from '@inertiajs/vue3'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { StockLocation, StockManagementRoutes } from '@/types/Inventory/StocksManagement'
import { notify } from '@kyvg/vue3-notification'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import { stockLocation } from '@/types/StockLocation'
library.add(faDotCircle, fasDotCircle, faUnlink, faExclamationTriangle, faUndo, faPlus, faSeedling, faTrash)

const layout = inject('layout', layoutStructure)

const props = defineProps<{
    locations: StockLocation[]
    routes: StockManagementRoutes
}>()


const emits = defineEmits(['close', 'confirm-open', 'confirm-close'])

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

const isConfirmOpen = ref(false)

const newLocation = ref<stockLocation | null>(null)
const handleUnlink = (loc: { id: any }) => {
    router.delete(
        route(props.routes.disassociate_location_route.name, {
            locationOrgStock: loc.id
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Location unlinked successfully"),
                    type: "success"
                })
            }
        }
    )
}
</script>

<template>
    <div class="space-y-6">
        <!-- V-FOR 1: Existing locations -->
        <div class="flex flex-col gap-y-6">
            <template v-if="props.locations.length > 0">
                <div v-for="(loc, idx) in props.locations" :key="'existing-' + loc.id"
                    class="grid grid-cols-7 border-b pb-2 items-center gap-1">
                    <div class="col-span-2 md:col-span-5 flex items-center gap-x-2">
                        {{ loc.code }}
                    </div>
                    <div class="col-span-3 md:col-span-1">
                        <span class="text-sm italic text-gray-400">
                            {{ trans("Current Stock") }} {{ Number(loc.quantity) }}
                        </span>
                    </div>
                    <div class="col-span-2 md:col-span-1 flex justify-end items-center gap-x-2">
                        <ModalConfirmationDelete
                            v-if="Number(loc.quantity) > 0"
                            :routeDelete="{
                                name: props.routes.disassociate_location_route.name,
                                parameters: { locationOrgStock: loc.id }
                            }"
                            :title="trans('Are you sure you want to unlink location?')"
                           :description="trans(
                                ':qty stock will be removed and marked as lost!',
                                { qty: Number(loc.quantity) }
                            )"
                            isFullLoading
                            :noLabel="trans('Yes, unlink location :xloc', { xloc: loc.code })"
                            noIcon="fal fa-unlink"
                        >
                        <!-- x  -->
                            <template #default="{ isOpenModal, changeModel, isLoadingdelete }">
                                <div v-if="layout.app.environment === 'local'" @click="() => {
                                    changeModel()
                                }" xclick="handleUnlink(loc)" class="cursor-pointer text-red-500 opacity-50 hover:opacity-100" v-tooltip="trans('Unlink Location')">
                                    <LoadingIcon v-if="isLoadingdelete" />
                                    <FontAwesomeIcon v-else icon="fal fa-unlink" class="" fixed-width aria-hidden="true" />
                                </div>
                                <FontAwesomeIcon v-else :icon="faBan" class="text-red-500" v-tooltip="'Work in Progress. Remember to disable this on Production when done'"/>
                            </template>
                        </ModalConfirmationDelete>
                         <div
                            v-else
                            v-if="layout.app.environment === 'local'"
                            @click="handleUnlink(loc)"
                            class="cursor-pointer text-red-500 opacity-50 hover:opacity-100"
                            v-tooltip="trans('Unlink Location (no stock)')"
                        >
                            <FontAwesomeIcon icon="fal fa-unlink" fixed-width />
                        </div>
                    </div>
                </div>
            </template>
            <div
            v-else
                class="flex flex-col items-center justify-center text-center py-10 border border-dashed border-gray-300 rounded-lg"
            >
                <div class="text-gray-600 font-medium">
                    {{ trans("No locations available") }}
                </div>

                <div class="text-sm text-gray-400 mt-1">
                    {{ trans("You haven't added any locations yet") }}
                </div>
            </div>
        </div>
        <!-- Section: buttons -->
        <div class="relative flex gap-x-2 isolate z-30 mt-4 justify-self-end">
            <Button :label="trans('Cancel')" type="cancel" @click="() => emits('close')" />
        </div>

    </div>
</template>