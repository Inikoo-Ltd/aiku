<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ref } from "vue"
import ButtonEditTable from "@/Components/ButtonEditTable.vue"
import Popover from '@/Components/Popover.vue'
import SelectQuery from '@/Components/SelectQuery.vue'
import { router, useForm } from "@inertiajs/vue3"
import { routeType } from "@/types/route"
import { Pallet } from "@/types/Pallet"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimesSquare } from "@fas"
import { faTrashAlt, faPaperPlane, faInventory } from "@far"
import { faSignOutAlt, faTruckLoading, faPencil, faTimes } from "@fal"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
library.add( faTrashAlt, faSignOutAlt, faPaperPlane, faInventory, faTruckLoading, faPencil, faTimesSquare, faTimes )

const props = defineProps<{
    pallet: Pallet
    locationRoute?: routeType
    noButton?: boolean
    noFetchOnMounted?: boolean
    initOptions?: {}[]
}>()

const emits = defineEmits<{
    (e: 'renderTableKey'): void
}>()
const location = useForm({ ...props.pallet })
const error = ref({})

/* const isPalletDamaged = ref(props.pallet.state === 'damaged') */

// const onSaveSuccess = (closed: Function) => {
//     closed()
//     emits('renderTableKey')
//     error.value = {}
// }


// const onSaveError = (errorValue: any) => {
//     error.value = errorValue
// }

const isLoading = ref<string | boolean>(false)
const onChangeLocation = (closeModal?: Function) => {
    router.patch(
        route(props.pallet['bookInRoute'].name, props.pallet['bookInRoute'].parameters),
        { location_id: location.data().location_id},
        {
            preserveScroll: true,
            onStart: () => { isLoading.value = true },
            onSuccess: () => {
                if (closeModal) { 
                    closeModal()
                }
                emits('renderTableKey')
                error.value = {}
            },
            onError: errors => {
                error.value = errors
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set location"),
                    type: "error"
                })
            },
            onFinish: () => { isLoading.value = false },
        }
    )
}

</script>

<template>
    <div class="relative w-64">
    <!-- <pre>{{ pallet.state }}</pre> -->
        <SelectQuery
            v-if="props.noButton"
            :urlRoute="route(locationRoute?.name, locationRoute?.parameters)"
            :value="location"
            :placeholder="'Select location'"
            :required="true"
            :trackBy="'code'"
            :label="'code'"
            :valueProp="'id'"
            :closeOnSelect="true"
            :clearOnSearch="false"
            :fieldName="'location_id'"
            @updateVModel="() => (onChangeLocation(), error.location_id = '')"
            :loadingCaret="!!isLoading"
            noFetchOnMounted
            :initOptions="initOptions"
        />

        <Popover v-else >
            <template #button>
                <!-- <Button :type="pallet.state == 'booked_in' ? 'primary' : 'tertiary'" :icon="['fal', 'inventory']"
                    tooltip="Set location for pallet" :key="pallet.index" :size="'xs'" /> -->
                <div v-if="pallet.location_code" class="text-gray-400">
                    {{ pallet.location_code }}
                    <FontAwesomeIcon icon='fal fa-pencil' size="sm" class='ml-1' fixed-width aria-hidden='true' />
                </div>

                <Button v-else-if="pallet.state !== 'not_received'"
                    type="primary"
                    :label="trans('Set location')"
                    :tooltip="trans('Set location for pallet')"
                    :key="pallet.index"
                    :size="'xs'"
                />
            </template>

            <template #content="{ close: closed }">
                <div class="w-[250px]">
                    <span class="text-xs px-1 my-2">{{ trans("Location") }}: </span>
                    <div>
                        <SelectQuery
                            :urlRoute="route(locationRoute?.name, locationRoute?.parameters)"
                            :value="location"
                            :placeholder="'Select location'"
                            :required="true"
                            :trackBy="'code'"
                            :label="'code'"
                            :valueProp="'id'"
                            :closeOnSelect="true"
                            :clearOnSearch="false"
                            :fieldName="'location_id'"
                            @updateVModel="() => (onChangeLocation(closed), error.location_id = '')"
                            :loadingCaret="!!isLoading"
                        />
                        
                       <!--  <div class="flex gap-x-1 items-center mt-2 pl-0.5">
                            <input v-model="isPalletDamaged" type="checkbox" id="checkboxLocation" class="rounded border-gray-300 text-red-500 shadow-sm focus:border-indigo-300 focus:ring-3 focus:ring-indigo-200/50">
                            <label for="checkboxLocation" class="select-none cursor-pointer text-gray-500">Set as damaged</label>
                        </div> -->

                        <p v-if="error.location_id" class="mt-2 text-sm text-red-600">{{ error.location_id }}</p>
                    </div>

                    <!-- Button: Save -->
                    <!-- <div class="flex justify-end mt-2">
                        <ButtonEditTable
                            type="primary"
                            @onSuccess="onSaveSuccess(closed)"
                            :icon="['fas', 'save']"
                            tooltip="Save location"
                            :key="pallet.index"
                            full
                            :disabled="!location.location_id"
                            @onError="onSaveError"
                            :dataToSubmit="{ location_id: location.data().location_id}"
                            routeName="bookInRoute"
                            :data="pallet" />
                    </div> -->
                </div>
            </template>
        </Popover>
    </div>
</template>