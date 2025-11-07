<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { ref, watch } from 'vue'
import { routeType } from "@/types/route"
import {Datum, stockLocation} from "@/types/StockLocation"
import { cloneDeep } from "lodash-es"
import Select from '@/Components/Forms/Fields/Select.vue'
import Popover from '@/Components/Popover.vue'
import { useForm, router } from '@inertiajs/vue3'
import Button from "@/Components/Elements/Buttons/Button.vue";
import { notify } from "@kyvg/vue3-notification"
import InfoCard from '@/Components/StockCard/InfoCard.vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faShoppingBasket, faClock, faEllipsisV, } from '@far'
import { faForklift } from '@fad'
import { faStickyNote, faClipboard, faInventory, faForklift as falForklift } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'


library.add(faShoppingBasket, faStickyNote, faClock, faEllipsisV, faClipboard, faInventory, faForklift, falForklift)

const props = defineProps<{
    data: stockLocation,
    locationRoute: routeType
    associateLocationRoute: routeType,
    disassociateLocationRoute: routeType,
    auditRoute: routeType,
    moveLocationRoute: routeType
}>();


const cloneData = ref(cloneDeep(props.data))
const loading = ref(false)
const form = useForm({
    newLocation: null
})

const sendMoveStock = (location : Datum, realQty = 0, close = () => null) => {
    router.patch(route(props.moveLocationRoute.name, { locationOrgStock: location.id, targetLocationOrgStock: form.newLocation }),
        { quantity: realQty - location.quantity },
        {
            onBefore: () => { loading.value = true },
            onSuccess: () => {
                form.reset('newLocation'),
                    loading.value = false,
                    close()
            },
            onError: () => {
                notify({
                    title: "Failed",
                    text: "failed move stock",
                    type: "error"
                })
                loading.value = false
            }

        })
}


watch(
    () => props.data,
    (newData) => {
        cloneData.value = cloneDeep(newData);
    },
    { deep: true }  // Add this option if you want to watch deeply nested changes
)

</script>


<template>
    <InfoCard v-bind="{...props, data : cloneData}">
        <template #Quantity="{ itemData : location, index }">
            <div class="flex justify-end w-1/4">
                <div class="flex justify-end">
                    <div>
                        <PureInputNumber v-model="location.quantity" :maxValue="data.locations.data[index].quantity" />
                        <p v-if="data.locations.data[index].quantity != location.quantity"
                            class="text-orange-400 text-xs mt-2">stock to be moved : {{
                                data.locations.data[index].quantity -
                                location.quantity }}</p>
                    </div>


                    <span class="ml-2 my-auto shrink-0">
                        <Popover v-if="data.locations.data[index].quantity != location.quantity">
                            <template #button="{ open, close }">
                                <button class="h-6 my-auto align-bottom text-center" type="submit">
                                    <FontAwesomeIcon :icon="faForklift" class="h-6"
                                        :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
                                </button>
                            </template>

                            <template #content="{ open, close }">
                                <div class="w-72">
                                    <div class="mb-3">
                                        Move {{ data.locations.data[index].quantity -
                                            location.quantity }} stock to :
                                    </div>
                                    <div class="mb-3">
                                        <Select :form="form" :fieldName="'newLocation'" :options="data.locations.data"
                                            :fieldData="{
                                                placeholder: 'select Location',
                                                searchable: true,
                                                labelProp: 'code',
                                                valueProp: 'id'
                                            }" />
                                    </div>
                                    <div class="flex justify-end">
                                        <Button :loading="loading" type="save"
                                            @click="() => sendMoveStock(location, data.locations.data[index].quantity ,close)" />
                                    </div>
                                </div>
                            </template>
                        </Popover>
                        <FontAwesomeIcon v-else :icon="falForklift" class="h-6 text-gray-300" aria-hidden="true" />
                    </span>
                </div>
            </div>
        </template>
    </InfoCard>
</template>