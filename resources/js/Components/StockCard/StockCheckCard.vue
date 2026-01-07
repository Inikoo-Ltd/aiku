<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'  // Import router from inertia
import { routeType } from "@/types/route"
import { cloneDeep } from "lodash-es"
import { notify } from "@kyvg/vue3-notification"
import InfoCard from '@/Components/StockCard/InfoCard.vue'
import { stockLocation, Datum } from "@/types/StockLocation"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import { faShoppingBasket, faClock, faEllipsisV, } from '@far'
import { faSave, faSpinnerThird } from '@fad'
import { faStickyNote, faClipboard, faInventory, faForklift, faSave as falSave } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faShoppingBasket, faStickyNote, faClock, faEllipsisV, faClipboard, faInventory, faForklift, falSave, faSave, faSpinnerThird)

const props = defineProps<{
    data: stockLocation,
    locationRoute: routeType,
    associateLocationRoute: routeType,
    disassociateLocationRoute: routeType,
    auditRoute: routeType,
    moveLocationRoute: routeType
}>();

const cloneData = ref(cloneDeep(props.data))

watch(
    () => props.data,
    (newData) => {
        cloneData.value = cloneDeep(newData);
    },
    { deep: true }
)

const updateQuantity = (location : Datum) => {
    // Use router.patch to send the updated quantity
    router.patch(
        route(props.auditRoute.name, { locationOrgStock: location.id }),  // Dynamic route for the patch
        { quantity: location.quantity },  // Send the updated quantity
        {
            onBefore: () => location.loading = true,
            onSuccess: () => {
                location.loading = false
            },
            onError: (error) => {
                location.loading = false
                notify({
                    title: "Failed",
                    text: error.quantity ? error.quantity : "failed audit quantity",
                    type: "error"
                })
            }
        }
    );
};
</script>



<template>
    <InfoCard v-bind="{ ...props, data: cloneData }">
        <template #Quantity="{ itemData: location, index }">
            <div class="flex justify-end w-1/4">
                <div class="flex justify-end gap-3">

                    <!-- Bind the quantity input to location.quantity -->
                        <PureInputNumber v-model="location.quantity" minValue="0" />

                    <span class="ml-2 my-auto shrink-0">
                        <!-- Show button if quantity has changed -->
                        <button v-if="data.locations.data[index].quantity != location.quantity"
                            @click="updateQuantity(location)" class="h-9 align-bottom text-center" type="button">
                            <!-- Show spinner while loading -->
                            <FontAwesomeIcon v-if="location.loading" icon='fad fa-spinner-third'
                                class='text-2xl animate-spin' fixed-width aria-hidden='true' />
                            <!-- Show save icon -->
                            <FontAwesomeIcon v-else :icon="faSave" class="h-8"
                                :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
                        </button>
                        <!-- Gray out save icon if no changes -->
                        <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
                    </span>
                </div>
            </div>
        </template>
    </InfoCard>
</template>
