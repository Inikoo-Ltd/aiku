<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUser, faBuilding, faEnvelope, faPhone } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import Tooltip from 'primevue/tooltip'

library.add(faUser, faBuilding, faEnvelope, faPhone)

const props = defineProps<{
    data: {}
}>()

console.log('RetinaB2BDashboard', props)
</script>

<template>
    <div class="p-8">
        <!-- Customer Contact Information -->
        <div v-if="data?.customer" class="mb-8 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">{{ trans("Customer Information") }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div v-if="data.customer.contact_name" class="flex items-center">
                    <FontAwesomeIcon 
                        icon="fas fa-user" 
                        class="text-gray-600 mr-2 w-4 h-4"
                        v-tooltip="trans('Contact Name')"
                    />
                    <span class="text-gray-900">{{ data.customer.contact_name }}</span>
                </div>
                <div v-if="data.customer.company_name" class="flex items-center">
                    <FontAwesomeIcon 
                        icon="fas fa-building" 
                        class="text-gray-600 mr-2 w-4 h-4"
                        v-tooltip="trans('Company Name')"
                    />
                    <span class="text-gray-900">{{ data.customer.company_name }}</span>
                </div>
                <div v-if="data.customer.email" class="flex items-center">
                    <FontAwesomeIcon 
                        icon="fas fa-envelope" 
                        class="text-gray-600 mr-2 w-4 h-4"
                        v-tooltip="trans('Email')"
                    />
                    <span class="text-gray-900">{{ data.customer.email }}</span>
                </div>
                <div v-if="data.customer.phone" class="flex items-center">
                    <FontAwesomeIcon 
                        icon="fas fa-phone" 
                        class="text-gray-600 mr-2 w-4 h-4"
                        v-tooltip="trans('Phone')"
                    />
                    <span class="text-gray-900">{{ data.customer.phone }}</span>
                </div>
            </div>
        </div>

        <div>
            <h1 class="text-4xl mb-4">Hello, <span class="font-bold">{{ data?.customer?.contact_name }}</span>!</h1>
            <p>
                Welcome to the <span class="bg-orange-500 px-1 py-0.5 text-white">E-commerce</span> dashboard. Here you can manage your business-to-business operations.
            </p>
        </div>
        
        <!-- resources/js/Pages/Retina/Dashboard/RetinaB2BDashboard.vue
        <pre class="bg-yellow-100">{{ data }}</pre> -->
        
    </div>
</template>
