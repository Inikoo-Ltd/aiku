<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDotCircle, faUnlink, faExclamationTriangle, faUndo, faPlus, faSeedling, faTrash } from "@fal"
import { faBan, faDotCircle as fasDotCircle } from "@fas"
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

const layout = inject('layout', layoutStructure)

const props = defineProps<{
    locations: StockLocation[]
    routes: StockManagementRoutes
}>()

console.log('editlocations', props)

const emits = defineEmits(['close'])

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
    <div class="space-y-2">
            <!-- V-FOR 1: Existing locations -->
            <div class="flex flex-col gap-y-3">
                <div v-for="(loc, idx) in props.locations" :key="'existing-' + loc.id"
                    class="grid grid-cols-7 gap-x-3 items-center gap-2">
                    <div class="col-span-2 flex items-center gap-x-2">
                        {{ loc.code }}
                    </div>
                    <div class="col-span-5 text-end">
                        <span class="text-sm italic text-gray-400">
                            {{ trans("Current Stock") }} {{ Number(loc.quantity) }}
                        </span>
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
                        v-if="layout.app.environment === 'local'"
                        @click="() => onAddNewLocation()"
                        :disabled="!newLocation"
                        :loading="isLoadingAddNewLocation"
                        :label="trans('Add')"
                        icon="fal fa-plus"
                    />
                    <FontAwesomeIcon v-else :icon="faBan" class="text-red-500" v-tooltip="'Work in Progress. Remember to disable this on Production when done'"/>
                </div>
            </div>
        <!-- Section: buttons -->
        <div class="relative flex gap-x-2 isolate z-30 mt-4 justify-self-end">
            <Button :label="trans('Cancel')" type="cancel" @click="() => emits('close')" />
        </div>

    </div>
</template>