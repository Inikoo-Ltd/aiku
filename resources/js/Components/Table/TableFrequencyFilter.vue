<script setup lang="ts">
import { ref, watch, onBeforeMount } from 'vue'
import { router } from '@inertiajs/vue3'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { trans } from 'laravel-vue-i18n'
import Select from 'primevue/select'

const props = defineProps<{
    tableName: string
}>()

// Frequency options matching TimeSeriesFrequencyEnum
const frequencyOptions = [
    { label: trans('Daily'), value: 'daily' },
    { label: trans('Weekly'), value: 'weekly' },
    { label: trans('Monthly'), value: 'monthly' },
    { label: trans('Quarterly'), value: 'quarterly' },
    { label: trans('Yearly'), value: 'yearly' },
]

const isLoadingReload = ref(false)
const selectedFrequency = ref('daily') // Default to daily

// Watch frequency changes and reload
watch(selectedFrequency, (newValue) => {
    router.reload({
        data: {
            frequency: newValue
        },
        onStart: () => {
            isLoadingReload.value = true
        },
        onFinish: () => {
            isLoadingReload.value = false
        }
    })
})

// Initialize from URL parameter
onBeforeMount(() => {
    const queryString = window.location.search
    const urlParams = new URLSearchParams(queryString)
    const frequencyParam = urlParams.get('frequency')

    if (frequencyParam) {
        selectedFrequency.value = frequencyParam
    }
})
</script>

<template>
    <div class="flex items-center gap-2 rounded-md" v-tooltip="trans('Select frequency')">
        <div class="relative">
            <Select
                v-model="selectedFrequency"
                :options="frequencyOptions"
                optionLabel="label"
                optionValue="value"
                :disabled="isLoadingReload"
                size="small"
            />
            <div v-if="isLoadingReload" class="absolute right-8 top-1/2 -translate-y-1/2 pointer-events-none">
                <LoadingIcon />
            </div>
        </div>
    </div>
</template>
