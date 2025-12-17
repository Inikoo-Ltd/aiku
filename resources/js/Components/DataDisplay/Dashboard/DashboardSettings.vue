<script setup lang="ts">
import { inject, onMounted, ref, nextTick } from "vue"
import { router } from "@inertiajs/vue3"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ToggleSwitch from "primevue/toggleswitch"
import { debounce } from 'lodash-es'
import axios from "axios"
import { RadioGroup, RadioGroupLabel, RadioGroupOption } from '@headlessui/vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCog, faChevronLeft, faChevronRight } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import { Intervals, Settings } from "@/types/Components/Dashboard"
import DashboardCustomDateRange from "./DashboardCustomDateRange.vue"
library.add(faCog, faChevronLeft, faChevronRight)

const props = defineProps<{
    intervals: Intervals
    settings: Settings
    currentTab: string
}>()

const layout = inject("layout", layoutStructure)
const isLoadingOnTable = inject("isLoadingOnTable", ref(false))
const isSectionVisible = ref(false)

// Overflow detection
const navElement = ref<HTMLElement | null>(null)
const hasOverflowLeft = ref(false)
const hasOverflowRight = ref(false)

const checkOverflow = () => {
    if (!navElement.value) return

    const element = navElement.value
    hasOverflowLeft.value = element.scrollLeft > 0
    hasOverflowRight.value = element.scrollLeft < (element.scrollWidth - element.clientWidth)
}

const scrollLeft = () => {
    if (navElement.value) {
        navElement.value.scrollBy({ left: -200, behavior: 'smooth' })
    }
}

const scrollRight = () => {
    if (navElement.value) {
        navElement.value.scrollBy({ left: 200, behavior: 'smooth' })
    }
}

// Section: Interval
const storeIntervalCode = debounce((interval_code) => {
    axios.patch(
        route("grp.models.profile.update"),
        {
            settings: {
                selected_interval: interval_code,
            },
        }
    )
}, 1500)

const isLoadingInterval = ref<string | null>(null)

const updateInterval = (interval_code: string) => {
    props.intervals.value = interval_code
    storeIntervalCode(interval_code)
}

const isLoadingToggle = ref<string | null>(null)

const updateToggle = async (key: string, value: string, valLoading: string, isAxios?: boolean) => {
    if (isAxios) {  // use Axios ()
        isLoadingToggle.value = valLoading
        isLoadingOnTable.value = true
        await axios.patch(route("grp.models.profile.update"), {
            settings: {
                [key]: value,
            },
        }).then(() => {
            isLoadingToggle.value = null
            props.settings[key].value = value
        }).catch(() => {

        })
        isLoadingToggle.value = null
        isLoadingOnTable.value = false
    } else {  // use Inertia
        router.patch(
            route("grp.models.profile.update"),
            {
                settings: {
                    [key]: value,
                },
            },
            {
                onStart: () => {
                    isLoadingToggle.value = valLoading
                    isLoadingOnTable.value = true
                },
                onFinish: () => {
                    isLoadingToggle.value = null
                    isLoadingOnTable.value = false
                },
                preserveScroll: true,
            }
        )
    }
}

// Section: update currency_type (grp, org)
const debStoreCurrencyType = debounce((value: string) => {
    axios.patch(route("grp.models.profile.update"), {
        settings: {
            currency_type: value,
        },
    })
}, 1500)

const updateCurrencyType = (value: string) => {
    props.settings.currency_type.value = value
    debStoreCurrencyType(value)
}

// Section: update data_display_type (minified, full)
const debStoreDataDisplayType = debounce((value: string) => {
    axios.patch(route("grp.models.profile.update"), {
        settings: {
            data_display_type: value,
        },
    })
}, 1500)

const updateDataDisplayType = (value: string) => {
    props.settings.data_display_type.value = value
    debStoreDataDisplayType(value)
}

onMounted(() => {
    nextTick(() => {
        checkOverflow()
        window.addEventListener('resize', checkOverflow)
    })
})
</script>

<template>
    <div class="relative px-1 md:px-4 md:mt-4">
        <div class="mb-2 flex justify-between gap-2">
            <!-- Section: Period options list with overflow indicators -->
            <div class="relative flex-1 min-w-0">
                <!-- Left overflow indicator -->
                <transition name="fade">
                    <div v-if="hasOverflowLeft"
                         @click="scrollLeft"
                         class="absolute left-0 top-0 bottom-0 z-10 flex items-center cursor-pointer bg-gradient-to-r from-white via-white to-transparent pl-1 pr-3 sm:pr-6">
                        <div class="bg-indigo-500 text-white rounded-full p-1 sm:p-1.5 shadow-lg hover:bg-indigo-600 transition-colors flex items-center min-h-full px-2">
                            <FontAwesomeIcon icon="far fa-chevron-left" class="text-[10px] sm:text-xs" />
                        </div>
                    </div>
                </transition>

                <!-- Right overflow indicator -->
                <transition name="fade">
                    <div v-if="hasOverflowRight"
                         @click="scrollRight"
                         class="absolute right-0 top-0 bottom-0 z-10 flex items-center cursor-pointer bg-gradient-to-l from-white via-white to-transparent pr-1 pl-3 sm:pl-6">
                        <div class="bg-indigo-500 text-white rounded-full p-1 sm:p-1.5 shadow-lg hover:bg-indigo-600 transition-colors flex items-center min-h-full px-2">
                            <FontAwesomeIcon icon="far fa-chevron-right" class="text-[10px] sm:text-xs" />
                        </div>
                    </div>
                </transition>

                <nav
                    ref="navElement"
                    @scroll="checkOverflow"
                    class="isolate rounded border py-1 px-1 sm:px-2 flex gap-1 items-center w-full overflow-x-scroll scrollbar-hide"
                    aria-label="Tabs">
                    <div
						v-if="
							(layout.currentRoute === 'grp.dashboard.show') ||
							(layout.currentRoute === 'grp.org.dashboard.show') ||
							(layout.currentRoute === 'grp.masters.dashboard')
						"
					>
                        <DashboardCustomDateRange :intervals="intervals" />
                    </div>

                    <div
                        v-for="(interval, idxInterval) in intervals.options"
                        :key="idxInterval"
                        @click="updateInterval(interval.value)"
                        :class="[
                            interval.value === intervals.value
                                ? 'bg-indigo-500 text-white font-medium'
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100',
                        ]"
                        v-tooltip="interval.label"
                        class="relative flex-grow flex-shrink-0 rounded py-1 md:py-1.5 px-2 sm:px-3 md:px-4 text-center text-xs sm:text-sm cursor-pointer select-none whitespace-nowrap">
                        <span :class="isLoadingInterval === interval.value ? 'opacity-0' : ''">
                            {{ interval.label }}
                        </span>
                        <span
                            class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2"
                            :class="isLoadingInterval === interval.value ? '' : 'opacity-0'">
                            <LoadingIcon />
                        </span>
                    </div>
                </nav>
            </div>

            <!-- Button: advanced settings -->
            <div
                v-tooltip="trans('Open advanced settings')"
                @click="isSectionVisible = !isSectionVisible"
                class="cursor-pointer p-2 rounded border flex items-center justify-center flex-shrink-0 self-start sm:self-auto"
                :class="isSectionVisible ? 'bg-indigo-200 text-indigo-500 border-transparent' : 'border-gray-300 text-gray-400 hover:bg-gray-200'">
                <FontAwesomeIcon icon="far fa-cog" fixed-width aria-hidden="true" class="text-xl sm:text-2xl" />
            </div>
        </div>

        <transition name="slide-to-right">
            <div v-show="isSectionVisible" id="dashboard-settings" class="flex flex-col lg:flex-row lg:flex-wrap justify-between lg:items-center gap-3 lg:gap-8 mb-2">

                <div class="flex items-center gap-x-2 sm:gap-x-4 text-xs sm:text-sm md:text-base overflow-x-auto">
                    <!-- Toggle: model_state -->
                    <Transition name="slide-to-right">
                        <div v-if="settings.model_state_type && currentTab === 'shops'" class="flex items-center gap-x-2 sm:gap-x-4 flex-shrink-0">
                            <p v-tooltip="settings.model_state_type.options[0].tooltip" class="leading-none whitespace-nowrap" :class="[ settings.model_state_type.options[0].value === settings.model_state_type.value ? 'font-semibold text-indigo-500 underline' : 'opacity-50', ]">
                                {{ settings.model_state_type.options[0].label }}
                            </p>
                            <ToggleSwitch
                                :modelValue="settings.model_state_type.value"
                                @update:modelValue="(value: any) => updateToggle(settings.model_state_type.id, value, `left_model_state_type`, false)"
                                :falseValue="settings.model_state_type.options[0].value"
                                :trueValue="settings.model_state_type.options[1]?.value"
                                :disabled="`left_model_state_type` === isLoadingToggle"
                            />
                            <p v-tooltip="settings.model_state_type.options[1]?.tooltip" class="whitespace-nowrap" :class="[ settings.model_state_type.options[1]?.value === settings.model_state_type.value ? 'font-semibold text-indigo-500 underline' : 'opacity-50', ]">
                                {{ settings.model_state_type.options[1]?.label }}
                            </p>
                        </div>
                    </Transition>
                </div>

                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-x-4 lg:gap-x-8 text-xs sm:text-sm md:text-base">
                    <!-- Toggle: data_display_type (minified, full) -->
                    <div v-if="settings.data_display_type" class="flex items-center gap-x-2 sm:gap-x-4 flex-shrink-0">
                        <p v-tooltip="settings.data_display_type.options[0].tooltip" class="whitespace-nowrap" :class="[ settings.data_display_type.options[0].value === settings.data_display_type.value ? 'font-semibold text-indigo-500 underline' : 'opacity-50', ]">
                            {{ settings.data_display_type.options[0].label }}
                        </p>
                        <ToggleSwitch
                            :modelValue="settings.data_display_type.value"
                            @update:modelValue="(value: any) => updateDataDisplayType(value)"
                            :falseValue="settings.data_display_type.options[0].value"
                            :trueValue="settings.data_display_type.options[1]?.value"
                            :disabled="`left_data_display_type` === isLoadingToggle"
                        />
                        <p v-tooltip="settings.data_display_type.options[1]?.tooltip" class="whitespace-nowrap" :class="[ settings.data_display_type.options[1]?.value === settings.data_display_type.value ? 'font-semibold text-indigo-500 underline' : 'opacity-50', ]">
                            {{ settings.data_display_type.options[1]?.label }}
                        </p>
                    </div>

                    <!-- Toggle: currency_type -->
                    <div v-if="settings.currency_type" class="flex items-center gap-x-2 sm:gap-x-4 w-full sm:w-auto">
                        <RadioGroup class="relative w-full sm:w-auto"
                                    :modelValue="settings.currency_type.value"
                                    @update:modelValue="(value: any) => updateCurrencyType(value)"
                        >
                            <div v-if="`right_currency_type` === isLoadingToggle" class="absolute inset-0 bg-black/50 rounded-md flex items-center justify-center z-10">
                                <LoadingIcon class="text-white text-xl m-auto" />
                            </div>
                            <RadioGroupLabel class="sr-only">Choose the radio</RadioGroupLabel>
                            <div class="flex gap-y-1 flex-wrap border border-gray-300 rounded-md overflow-hidden w-fit">
                                <RadioGroupOption
                                    as="template" v-for="(option) in settings.currency_type.options"
                                    :key="option.value"
                                    :value="option.value"
                                    v-slot="{ active, checked }"
                                >
                                    <div :class="[
                                            'cursor-pointer focus:outline-none flex items-center justify-center py-1 sm:py-2 md:py-3 px-2 sm:px-3 text-xs sm:text-sm font-medium whitespace-nowrap',
                                            checked ? 'bg-indigo-500 text-white' : ' bg-white text-gray-700 hover:bg-gray-200',
                                        ]"
                                         v-tooltip="option.tooltip"
                                    >
                                        <RadioGroupLabel as="span">{{ option.label }}</RadioGroupLabel>
                                    </div>
                                </RadioGroupOption>
                            </div>
                        </RadioGroup>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
:deep(#dashboard-settings) {
    --p-toggleswitch-background: v-bind('layout?.app?.theme[4]');
    --p-toggleswitch-hover-background: v-bind('layout?.app?.theme[2]');
    --p-toggleswitch-checked-background: v-bind('layout?.app?.theme[4]');
    --p-toggleswitch-checked-hover-background: v-bind('layout?.app?.theme[2]');
}

/* Hide scrollbar but keep functionality */
.scrollbar-hide {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;  /* Chrome, Safari and Opera */
}

/* Fade transition for overflow indicators */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
