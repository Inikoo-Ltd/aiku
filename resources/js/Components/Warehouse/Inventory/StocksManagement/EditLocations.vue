<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDotCircle, faUnlink, faExclamationTriangle, faUndo, faPlus, faSeedling, faTrash } from "@fal"
import { faDotCircle as fasDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, ref, inject } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { router } from '@inertiajs/vue3'
import { StockLocation, StockManagementRoutes } from '@/types/Inventory/StocksManagement'
import { notify } from '@kyvg/vue3-notification'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { Dialog } from 'primevue'
library.add(faDotCircle, fasDotCircle, faUnlink, faExclamationTriangle, faUndo, faPlus, faSeedling, faTrash)

const screenType = inject('screenType', ref('desktop'))

const props = defineProps<{
    locations: StockLocation[]
    routes: StockManagementRoutes
}>()


const emits = defineEmits(['close'])

const locationToUnlink = ref<StockLocation | null>(null)
const isConfirmUnlinkOpen = computed({
    get: () => locationToUnlink.value !== null,
    set: (value: boolean) => {
        if (!value) locationToUnlink.value = null
    }
})

const unlinkingLocationId = ref<any>(null)
const handleUnlink = (loc: { id: any }) => {
    router.delete(
        route(props.routes.disassociate_location_route.name, {
            locationOrgStock: loc.id
        }),
        {
            preserveScroll: true,
            onStart: () => {
                unlinkingLocationId.value = loc.id
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Location unlinked successfully"),
                    type: "success"
                })
                locationToUnlink.value = null
            },
            onFinish: () => {
                unlinkingLocationId.value = null
            }
        }
    )
}
</script>

<template>
    <div class="flex flex-col min-h-0 max-h-[65vh]">
        <!-- V-FOR 1: Existing locations -->
        <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden flex flex-col gap-y-4 pr-4 pb-3">
            <template v-if="props.locations.length > 0">
                <div class="grid grid-cols-7 gap-x-4 border-b pb-2 pt-1 items-center gap-1 sticky top-0 z-10 bg-white">
                    <div class="col-span-2 md:col-span-4 flex items-center gap-x-2 font-medium">
                        {{ ctrans("Code") }}
                    </div>
                    <div class="col-span-3 md:col-span-2 font-medium text-right">
                        {{ ctrans("Current stock") }}
                    </div>
                    <div class="col-span-2 md:col-span-1 flex justify-end items-center gap-x-2 font-medium">
                        {{ ctrans("Actions") }}
                    </div>
                </div>

                <div v-for="(loc, idx) in props.locations" :key="'existing-' + loc.id"
                    class="grid grid-cols-7 gap-x-4 border-b border-dashed pb-2 items-center gap-1">
                    <div class="col-span-2 md:col-span-4 flex items-center gap-x-2">
                        {{ loc.code }}
                    </div>
                    <div class="col-span-3 md:col-span-2 text-right">
                        <span class="text-sm italic text-gray-400 text-right tabular-nums">
                            {{ Number(loc.quantity) }}
                        </span>
                    </div>
                    <div class="col-span-2 md:col-span-1 flex justify-end items-center gap-x-2">
                        <div
                            @click="unlinkingLocationId === loc.id ? null : (Number(loc.quantity) > 0 ? locationToUnlink = loc : handleUnlink(loc))"
                            class="cursor-pointer text-red-500 opacity-50 hover:opacity-100"
                            :class="{ 'pointer-events-none': unlinkingLocationId === loc.id }"
                            v-tooltip="Number(loc.quantity) > 0 ? trans('Unlink Location') : trans('Unlink Location (no stock)')"
                        >
                            <LoadingIcon v-if="unlinkingLocationId === loc.id" />
                            <FontAwesomeIcon v-else icon="fal fa-unlink" fixed-width />
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
        <div class="shrink-0 relative flex gap-x-2 isolate z-30 pt-3 mt-2 border-t bg-white">
            <Button :label="trans('Cancel')" type="tertiary" icon="far fa-arrow-left" @click="() => emits('close')" />
        </div>

        <Dialog
            v-model:visible="isConfirmUnlinkOpen"
            modal
            :header="trans('Are you sure you want to unlink location?')"
            :dismissableMask="screenType === 'desktop'"
            :draggable="false"
            :style="{ width: '32rem' }"
            :breakpoints="{ '640px': '90vw' }"
        >
            <div class="flex items-start gap-x-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-600" fixed-width aria-hidden="true" />
                </div>
                <p class="text-sm text-gray-500">
                    {{ trans(':qty stock will be removed and marked as lost!', { qty: Number(locationToUnlink?.quantity ?? 0) }) }}
                </p>
            </div>

            <div class="mt-5 flex flex-row-reverse gap-2">
                <Button
                    type="red"
                    icon="fal fa-unlink"
                    :label="trans('Yes, unlink location :xloc', { xloc: locationToUnlink?.code ?? '' })"
                    :loading="unlinkingLocationId === locationToUnlink?.id"
                    @click="() => locationToUnlink && handleUnlink(locationToUnlink)"
                />
                <Button
                    type="tertiary"
                    :label="trans('Cancel')"
                    @click="locationToUnlink = null"
                />
            </div>
        </Dialog>
    </div>
</template>
