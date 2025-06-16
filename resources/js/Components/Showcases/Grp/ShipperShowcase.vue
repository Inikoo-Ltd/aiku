<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Thu, 25 May 2023 15:03:05 Central European Summer Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { routeType } from '@/types/route'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLink} from '@far'
import { faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import AddressLocation from '@/Components/Elements/Info/AddressLocation.vue'
import { trans } from 'laravel-vue-i18n'
import { ref } from 'vue'
import Modal from '@/Components/Utils/Modal.vue'
import CustomerAddressManagementModal from '@/Components/Utils/CustomerAddressManagementModal.vue'
import { Address, AddressManagement } from '@/types/PureComponent/Address'

library.add(faLink, faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt)

interface Shipper {
    slug: string
    code: string
    name: string
    contact_name: string
    company_name: string
    email: string
    phone: string
}

const props = defineProps<{
    data: Shipper,
    tab: string
}>()
</script>

<template>
    <!-- Section: Stats box -->
    <div class="px-4 py-5 md:px-6 lg:px-8 grid grid-cols-2 gap-x-8 gap-y-3">

        <!-- Section: Profile box -->
        <div >
            <div class="rounded-lg shadow-sm ring-1 ring-gray-900/5">
                <dl class="flex flex-wrap">
                    <!-- Section: Field -->
                    <div class="flex flex-col gap-y-3 border-t border-gray-900/5 w-full py-6">
                        <!-- Field: Contact name -->
                        <div v-if="data?.shipper?.contact_name" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Contact name')" class="flex-none">
                                <span class="sr-only">Contact name</span>
                                <FontAwesomeIcon icon='fal fa-building' class='text-gray-400' fixed-width aria-hidden='true' />
                            </dt>
                            <dd class="text-gray-500">{{ data?.shipper?.contact_name }}</dd>
                        </div>

                        <!-- Field: Conmpany name -->
                        <div v-if="data?.shipper?.company_name" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Company name')" class="flex-none">
                                <span class="sr-only">Company name</span>
                                <FontAwesomeIcon icon='fal fa-building' class='text-gray-400' fixed-width aria-hidden='true' />
                            </dt>
                            <dd class="text-gray-500">{{ data?.shipper?.company_name }}</dd>
                        </div>

                        <!-- Field: Website -->
                        <div v-if="data?.shipper?.email" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Website')" class="flex-none">
                                <span class="sr-only">Website</span>
                                <FontAwesomeIcon icon='fal fa-envelope' class='text-gray-400' fixed-width aria-hidden='true' />
                            </dt>
                            <dd class="text-gray-500">{{ data?.shipper?.website }}</dd>
                        </div>

                        <!-- Field: Phone -->
                        <div v-if="data?.shipper?.phone" class="flex items-center w-full flex-none gap-x-4 px-6">
                            <dt v-tooltip="trans('Phone')" class="flex-none">
                                <span class="sr-only">Phone</span>
                                <FontAwesomeIcon icon='fal fa-phone' class='text-gray-400' fixed-width aria-hidden='true' />
                            </dt>
                            <dd class="text-gray-500">{{ data?.shipper?.phone }}</dd>
                        </div>
                    </div>
                </dl>
            </div>
        </div>


    </div>
</template>
