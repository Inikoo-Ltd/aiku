<script setup lang='ts'>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationTriangle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
library.add(faExclamationTriangle)

const props = defineProps<{
    alert: {
        status: string
        title?: string
        description?: string
    }
}>()

const getAlertColor = (alert: string) => {
    switch (alert) {
        case 'success':
            return 'bg-green-100 text-green-600'
        case 'warning':
            return 'bg-amber-100 text-amber-600'
        case 'danger':
            return 'bg-red-200 text-red-600'
    }
}

const getAlertBorder = (alert: string) => {
    switch (alert) {
        case 'success':
            return '#22c55e'
        case 'warning':
            return '#fbbf24'
        case 'danger':
            return '#f87171'
    }
}
</script>

<template>
    <div class="flex gap-x-3 p-4 rounded-sm"
        :class="getAlertColor(alert.status)"
        :style="{
            'border': `1px solid ${getAlertBorder(alert.status)}`
        }"    
    >
        <div class="">
            <FontAwesomeIcon icon='fas fa-exclamation-triangle' size="lg" class='' fixed-width aria-hidden='true' />
        </div>

        <div class="">
            <h3 class="text-base font-semibold">{{ alert.title || trans('Attention needed') }}</h3>
            <div class="text-sm opacity-80 ">
                {{ alert.description }}
            </div>
        </div>
    </div>
</template>