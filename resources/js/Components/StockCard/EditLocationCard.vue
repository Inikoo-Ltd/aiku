<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import SelectQuery from '@/Components/SelectQuery.vue'
import Select from '@/Components/Forms/Fields/Select.vue'
import Button from "@/Components/Elements/Buttons/Button.vue";
import { notify } from "@kyvg/vue3-notification"
import { Link, useForm } from '@inertiajs/vue3'
import { routeType } from "@/types/route"
import { ref } from 'vue'
import {Datum, stockLocation} from "@/types/StockLocation"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faShoppingBasket } from '@far'
import { faUnlink } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'


library.add(faShoppingBasket, faUnlink)

const props = defineProps<{
    data: stockLocation
    locationRoute: routeType
    associateLocationRoute: routeType,
    disassociateLocationRoute: routeType,
    auditRoute: routeType,
}>();

const loading = ref(false)
const typeOptions = [{
    label: 'Storing',
    value: 'storing'
}, {
    label: 'Picking',
    value: 'picking'
}
]
const form = useForm({
    location_org_stock: null,
    type : 'storing'
})

const AssociateLocation = () => {
    form.post(route(
        props.associateLocationRoute.name,
        { ...props.associateLocationRoute.parameters, location: form.location_org_stock }
    ),
        {
            onBefore: () => { loading.value = true },
            onSuccess: () => { form.reset('location_org_stock'), loading.value = false },
            onError: () => {
                notify({
                    title: "Failed",
                    text: "failed to add location",
                    type: "error"
                })
                loading.value = false
            }
        })
}



</script>


<template>
    <ul class="divide-y divide-gray-100  bg-white shadow-sm ring-1 ring-gray-900/5">
        <li v-for="location in data.locations.data" :key="location.code"
            class="relative flex justify-between gap-x-6 px-4 py-4 hover:bg-gray-50 sm:px-6">

            <div class="flex items-center w-1/2 gap-x-4">
                <!-- Location Icon -->
                <FontAwesomeIcon class="h-5 w-5 flex-none rounded-full bg-gray-50" :icon="faShoppingBasket" />

                <div class="flex-auto">
                    <div class="text-sm font-semibold leading-6 text-gray-900">
                        {{ location.location.code }}
                        <span class="text-gray-400">
                            Current Stock {{ parseInt(location.quantity) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right Side: Stock Information (Duplicated) -->
            <div class="flex justify-end w-1/2">
                <div class="flex justify-end">
                    <div class="text-sm font-semibold leading-6 text-gray-900">
                        <Link method="delete"
                            :href="route(disassociateLocationRoute.name, {locationOrgStock : location.id})"
                            type="button">
                        <button v-tooltip="'Unlink Location'"
                            class="inline-flex w-full justify-center rounded-md px-4 py-2 text-sm font-medium  hover:bg-red-400 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white/75">
                            <FontAwesomeIcon :icon="faUnlink" />
                        </button>
                        </Link>
                    </div>
                </div>
            </div>
        </li>
        <li class="flex justify-between gap-x-6 px-4 py-4 hover:bg-gray-50 sm:px-6">
            <div class="flex items-center w-full gap-x-4">
                <div class="flex flex-auto items-center gap-4 w-full">
                    <div class="flex-1">
                        <span class="text-xs py-2 block">Location</span>
                        <SelectQuery :urlRoute="route(locationRoute?.name, locationRoute?.parameters)" :value="form"
                            :placeholder="'Select location'" :required="true" :trackBy="'code'" :label="'code'"
                            :valueProp="'id'" :closeOnSelect="true" :clearOnSearch="false"
                            :fieldName="'location_org_stock'" />
                        <p class="text-xs text-red-500">{{ form.errors.location_org_stock }}</p>
                    </div>
                    <div class="flex-1">
                        <span class="text-xs py-2 block">Type</span>
                        <Select :form="form" :fieldName="'type'" :options="typeOptions" :fieldData="{
                            placeholder: 'select type',
                            searchable: true,
                            required : true,
                        }" />
                    </div>
                    <Button type="create" label="Add Location" @click="AssociateLocation" class="self-end" />
                </div>
            </div>
        </li>

    </ul>
</template>
