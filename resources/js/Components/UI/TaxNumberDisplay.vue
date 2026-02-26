<!--
* Author: Vika Aqordi
* Created on: 2025-09-24 10:07
* Github: https://github.com/aqordeon
* Copyright: 2025
-->

<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import { Tooltip } from 'floating-vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle, faCheckCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHistory } from '@fal'
library.add(faExclamationCircle, faCheckCircle)

interface TaxNumberResource {
    number: string
    status: 'valid' | 'invalid' | 'pending'
    valid: boolean
    country: {
        data: {
            name: string
            code: string
        }
    } | null
    checked_at: string | null
}

const props = defineProps<{
    tax_number: TaxNumberResource
    show_view_history_button: boolean|null
    view_history_link: string|null
}>()

// Tax number validation helper functions
const formatDate = (dateString: string | null) => {
    if (!dateString) return null

    try {
        return useFormatTime(dateString, {
            formatTime: 'dd MMM yyyy',
        })
    } catch (error) {
        console.error('Error formatting date:', error)
        return dateString
    }
}

const getStatusIcon = (status: string, valid: boolean) => {
    if (status === 'invalid' || !valid) {
        return 'fa-exclamation-circle'
    }
    if (status === 'valid' || valid) {
        return 'fa-check-circle'
    }
    return 'fa-spinner-third'
}

const getStatusColor = (status: string, valid: boolean) => {
    if (status === 'invalid' || !valid) {
        return 'text-red-600'
    }
    if (status === 'valid' || valid) {
        return 'text-green-600'
    }
    return 'text-yellow-600'
}

const getStatusText = (status: string, valid: boolean) => {
    if (status === 'invalid' || !valid) {
        return trans('Invalid')
    }
    if (status === 'valid' || valid) {
        return trans('Valid')
    }
    return trans('Pending')
}

</script>

<template>
    <dd class="w-full">
        <div class="space-y-2">
            <!-- Tax Number Display -->
            <div class="font-medium">{{ tax_number.number }}</div>
            
            <!-- Validation Status Display -->
            <div class="px-3 py-2 bg-gray-50 rounded border">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <FontAwesomeIcon 
                            :icon="getStatusIcon(tax_number.status, tax_number.valid)"
                            :class="getStatusColor(tax_number.status, tax_number.valid)" 
                            class="text-sm"
                            fixed-width
                        />

                        <div class="space-y-2 whitespace-nowrap">
                            <p class="text-sm">
                                <span class="font-medium" :class="getStatusColor(tax_number.status, tax_number.valid)">
                                    {{ getStatusText(tax_number.status, tax_number.valid) }}
                                </span>

                                <!-- Country -->
                                <Tooltip v-if="tax_number.country" class="inline ml-1">
                                    <div class="inline hover:underline cursor-default">({{ tax_number.country.data.name }})</div>

                                    <template #popper>
                                        <div class="p-1 max-w-xs">
                                            <div class="space-y-2">
                                                <div class="text-sm space-y-1">
                                                    <p><span class="font-medium">{{ trans('Country') }}:</span> {{ tax_number.country.data.name }}</p>
                                                    <p><span class="font-medium">{{ trans('Country Code') }}:</span> {{ tax_number.country.data.code }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </Tooltip>

                                <!-- Last checked date -->
                                <span v-if="tax_number.checked_at"
                                    v-tooltip="trans('Last checked :date', { date: formatDate(tax_number.checked_at) || '-' })"
                                    class="ml-1 cursor-default hover:underline">
                                    {{ formatDate(tax_number.checked_at) }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <span v-if="show_view_history_button && view_history_link" class="justify-self-end ml-6 h-full whitespace-nowrap hover:underline cursor-pointer hover:text-gray-500" v-tooltip="trans('View Tax Number Validation History')" v-on:click="router.visit(route(view_history_link))">
                        <span class="text-xs h-full mr-1 hidden md:inline">
                            {{ trans('View History') }}
                        </span>
                        <FontAwesomeIcon :icon="faHistory"/>
                    </span>
                </div>
            </div>
        </div>
    </dd>
</template>