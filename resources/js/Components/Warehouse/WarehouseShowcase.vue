<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 18:37:23 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import SimpleBox from "@/Components/DataDisplay/SimpleBox.vue"
import { routeType } from "@/types/route"
import Fieldset from "primevue/fieldset"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faMapMarkedAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from "laravel-vue-i18n"
library.add(faMapMarkedAlt)

const props = defineProps<{
    data?: {
        xxx: {}
        address: {
            formatted_address: string
        }
        box_stats: {
            name: string
            number: number
            route: routeType
            icon: {
                icon: string
                tooltip: string
            }
        }[]
    }
}>()
</script>


<template>
    <div class="grid grid-cols-2 gap-x-8 px-6">
        <div>
            <Fieldset legend="Address">
                <template #legend>
                    <div>
                        <FontAwesomeIcon icon="fal fa-map-marked-alt" class="text-gray-500" fixed-width aria-hidden='true' />
                        <span class="font-medium">&nbsp;{{ trans('Address') }}</span>
                    </div>
                </template>

                <div v-if="data?.address?.formatted_address" v-html="data?.address?.formatted_address" class="text-gray-500"></div>
                <div v-else class="italic opacity-60">
                    Address not set yet
                </div>
            </Fieldset>
        </div>

        <div class="mt-1.5">
            <SimpleBox v-if="data?.box_stats" :box_stats="data.box_stats" />
            <div v-else>
                Warehouse Showcase
            </div>
        </div>
    </div>
</template>
