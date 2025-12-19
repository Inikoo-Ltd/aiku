<script setup lang="ts">
import { onBeforeMount, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChartLine } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { trans } from 'laravel-vue-i18n'
import Select from 'primevue/select'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'

library.add(faChartLine)

const props = defineProps<{
    tableName: string
    defaultInterval?: string
}>()

const intervals = [
    { label: trans('All'), value: 'all' },
    { label: trans('Year to Date'), value: 'ytd' },
    { label: trans('Quarter to Date'), value: 'qtd' },
    { label: trans('Month to Date'), value: 'mtd' },
    { label: trans('Week to Date'), value: 'wtd' },
    { label: trans('Today'), value: 'tdy' },
    { label: trans('1 Year'), value: '1y' },
    { label: trans('1 Quarter'), value: '1q' },
    { label: trans('1 Month'), value: '1m' },
    { label: trans('1 Week'), value: '1w' },
    { label: trans('3 Days'), value: '3d' },
    { label: trans('Last Month'), value: 'lm' },
    { label: trans('Last Week'), value: 'lw' },
    { label: trans('Yesterday'), value: 'ld' },
]

const isLoadingReload = ref(false)
const selectedInterval = ref(props.defaultInterval || 'all')
const hasIntervalQuery = ref(false)

watch(selectedInterval, (newValue) => {
    router.reload({
        data: { interval: newValue },
        onStart: () => {
            isLoadingReload.value = true
        },
        onFinish: () => {
            isLoadingReload.value = false
        },
        onSuccess: () => {
            hasIntervalQuery.value = true
        },
        onError: (e) => {
            console.error('Error updating interval:', e)
        }
    })
})

const resetInterval = () => {
    selectedInterval.value = props.defaultInterval || 'all'
    router.reload({
        data: { interval: null },
        onStart: () => {
            isLoadingReload.value = true
        },
        onFinish: () => {
            isLoadingReload.value = false
            hasIntervalQuery.value = false
        }
    })
}

onBeforeMount(() => {
    const queryString = window.location.search
    const urlParams = new URLSearchParams(queryString)
    const intervalParam = urlParams.get('interval')

    if (intervalParam) {
        selectedInterval.value = intervalParam
        hasIntervalQuery.value = true
    }
})
</script>

<template>
    <div class="flex items-center gap-2 rounded-md" v-tooltip="trans('Filter data by interval')">
        <transition name="slide-fade">
            <div v-if="hasIntervalQuery"
                 class="flex items-center gap-1.5 px-2 py-1 bg-indigo-50 border border-indigo-200 rounded text-xs text-indigo-700 whitespace-nowrap">
                <span class="font-medium">
                    {{ intervals.find(i => i.value === selectedInterval)?.label || selectedInterval.toUpperCase() }}
                </span>
            </div>
        </transition>

        <Popover v-slot="{ open }" class="relative">
            <PopoverButton
                :class="open ? '' : ''"
                v-tooltip="trans('Filter by interval')"
                class="group inline-flex items-center rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75"
            >
                <div class="h-9 w-9 rounded flex justify-center items-center"
                    :class="hasIntervalQuery ? 'border border-indigo-300 bg-indigo-50 hover:bg-indigo-100 text-indigo-600' : 'border border-gray-300 hover:bg-gray-300 text-gray-600 hover:text-gray-200'"
                >
                    <FontAwesomeIcon v-if="!isLoadingReload" icon='fal fa-chart-line' class='cursor-pointer'
                        fixed-width aria-hidden='true' />
                    <LoadingIcon v-else />
                </div>
            </PopoverButton>

            <Transition name="headlessui">
                <PopoverPanel
                    class="bg-gray-50 border border-gray-300 rounded-md absolute right-0 z-10 mt-3 w-fit transform px-4 py-4"
                >
                    <div class="flex items-center gap-x-3 mb-3">
                        <Select
                            v-model="selectedInterval"
                            :options="intervals"
                            optionLabel="label"
                            optionValue="value"
                            :placeholder="trans('Select interval')"
                            class="w-64"
                        />

                        <div @click="resetInterval" class="text-red-400 hover:text-red-600 cursor-pointer text-sm">
                            {{ trans("Reset") }}
                        </div>
                    </div>

                    <div class="text-xs text-gray-500 mt-2">
                        {{ trans("Select the time period for sales and invoices metrics") }}
                    </div>
                </PopoverPanel>
            </Transition>
        </Popover>
    </div>
</template>

<style scoped>
.slide-fade-enter-active {
    transition: all 0.3s ease-out;
}

.slide-fade-leave-active {
    transition: all 0.2s cubic-bezier(1, 0.5, 0.8, 1);
}

.slide-fade-enter-from,
.slide-fade-leave-to {
    transform: translateX(10px);
    opacity: 0;
}

.headlessui-enter-active,
.headlessui-leave-active {
    transition: opacity 0.2s ease;
}

.headlessui-enter-from,
.headlessui-leave-to {
    opacity: 0;
}
</style>
