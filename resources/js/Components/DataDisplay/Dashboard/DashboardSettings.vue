<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { readableTextOn } from "@/Composables/useAppAccent"
import { useScrollArrows } from "@/Composables/useScrollArrows"
import { router } from "@inertiajs/vue3"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ToggleSwitch from "primevue/toggleswitch"
import { debounce } from 'lodash-es'
import axios from "axios"
import { RadioGroup, RadioGroupLabel, RadioGroupOption } from '@headlessui/vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCog, faChevronLeft, faChevronRight, faSyncAlt } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ctrans } from "@/Composables/useTrans"
import { Intervals, Settings } from "@/types/Components/Dashboard"
import DashboardCustomDateRange from "./DashboardCustomDateRange.vue"
import DashboardSettingToggle from "./DashboardSettingToggle.vue"
import DashboardSettingChoice from "./DashboardSettingChoice.vue"
library.add(faCog, faChevronLeft, faChevronRight, faSyncAlt)

const props = defineProps<{
    intervals: Intervals
    settings: Settings
    currentTab: string
    reloadOnly?: string[]
}>()

const emit = defineEmits<{
    (e: 'intervalChanged', value: string): void
}>()

const layout = inject("layout", layoutStructure)

const accentColor = computed(() => layout?.app?.theme?.[4] || "#6366f1")

const accentTextColor = computed(() => readableTextOn(accentColor.value))

const isLoadingOnTable = inject("isLoadingOnTable", ref(false))
const isSectionVisible = ref(false)

// Overflow detection
const navElement = ref<HTMLElement | null>(null)
const { canScrollLeft: hasOverflowLeft, canScrollRight: hasOverflowRight, scrollBy: scrollIntervals } = useScrollArrows(navElement)

const scrollLeft = () => scrollIntervals(-1)
const scrollRight = () => scrollIntervals(1)

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

    if (props.reloadOnly?.length) {
        isLoadingOnTable.value = true
        router.patch(
            route("grp.models.profile.update"),
            { settings: { selected_interval: interval_code } },
            {
                preserveScroll: true,
                preserveState: true,
                only: props.reloadOnly,
                onFinish: () => { isLoadingOnTable.value = false },
            }
        )
    } else if (props.currentTab === 'top_customers') {
        isLoadingOnTable.value = true
        router.patch(
            route("grp.models.profile.update"),
            { settings: { selected_interval: interval_code } },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['dashboard', 'top_customers'],
                onFinish: () => { isLoadingOnTable.value = false },
            }
        )
    } else if (props.currentTab === 'ordering') {
        isLoadingOnTable.value = true
        router.patch(
            route("grp.models.profile.update"),
            { settings: { selected_interval: interval_code } },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['stats', 'intervals'],
                onFinish: () => { isLoadingOnTable.value = false },
            }
        )
    } else if (props.currentTab === 'insights') {
        isLoadingOnTable.value = true
        router.patch(
            route("grp.models.profile.update"),
            { settings: { selected_interval: interval_code } },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['dashboard', 'offers'],
                onFinish: () => { isLoadingOnTable.value = false },
            }
        )
    } else {
        storeIntervalCode(interval_code)
    }

    emit('intervalChanged', interval_code)
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

const updatePartnersType = async (value: string) => {
    props.settings.partners_type.value = value
    isLoadingToggle.value = 'left_partners_type'
    isLoadingOnTable.value = true
    try {
        await axios.patch(route("grp.models.profile.update"), {
            settings: {
                partners_type: value,
            },
        })
    } finally {
        router.reload({
            onFinish: () => {
                isLoadingToggle.value = null
                isLoadingOnTable.value = false
            },
        })
    }
}

const isRefreshing = ref(false)

const refreshDashboard = () => {
    router.post(route("grp.models.dashboard.break_cache"), {}, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            isRefreshing.value = true
            isLoadingOnTable.value = true
        },
        onFinish: () => {
            isRefreshing.value = false
            isLoadingOnTable.value = false
        },
    })
}

// Section: update currency_type (grp, org)
const debStoreCurrencyType = debounce((value: string) => {
    axios.patch(route("grp.models.profile.update"), {
        settings: {
            [props.settings.currency_type.id]: value,
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

// Section: update top_customers_limit
const updateTopCustomersLimit = (value: number) => {
    props.settings.top_customers_limit.value = value
    isLoadingToggle.value = 'top_customers_limit'
    isLoadingOnTable.value = true
    router.patch(
        route("grp.models.profile.update"),
        {
            settings: {
                top_customers_limit: value,
            },
        },
        {
            onFinish: () => {
                isLoadingToggle.value = null
                isLoadingOnTable.value = false
            },
            preserveScroll: true,
            only: ['dashboard', 'top_customers'],
        }
    )
}

</script>

<template>
    <div class="relative px-3 sm:px-6 md:mt-1">
        <div class="mb-2 flex justify-between gap-2">
            <!-- Section: Period options list with overflow indicators -->
            <div class="relative flex-1 min-w-0">
                <!-- Left overflow indicator -->
                <transition name="fade">
                    <div v-if="hasOverflowLeft"
                         @click="scrollLeft"
                         class="absolute left-0 top-0 bottom-0 z-10 flex w-6 items-center justify-center cursor-pointer rounded-l bg-white text-gray-500 shadow-[6px_0_6px_-4px_rgba(0,0,0,0.12)] hover:text-gray-800">
                        <FontAwesomeIcon icon="far fa-chevron-left" class="text-xs" fixed-width />
                    </div>
                </transition>

                <!-- Right overflow indicator -->
                <transition name="fade">
                    <div v-if="hasOverflowRight"
                         @click="scrollRight"
                         class="absolute right-0 top-0 bottom-0 z-10 flex w-6 items-center justify-center cursor-pointer rounded-r bg-white text-gray-500 shadow-[-6px_0_6px_-4px_rgba(0,0,0,0.12)] hover:text-gray-800">
                        <FontAwesomeIcon icon="far fa-chevron-right" class="text-xs" fixed-width />
                    </div>
                </transition>

                <nav
                    ref="navElement"
                    class="isolate rounded border py-1 px-1 sm:px-2 flex gap-1 items-center w-full overflow-x-scroll scrollbar-hide"
                    aria-label="Tabs">
                    <div>
                        <DashboardCustomDateRange :intervals="intervals" :currentTab="currentTab" :reloadOnly="reloadOnly" />
                    </div>

                    <div
                        v-for="(interval, idxInterval) in intervals.options"
                        :key="idxInterval"
                        @click="updateInterval(interval.value)"
                        :class="[
                            interval.value === intervals.value
                                ? 'dashboard-accent font-medium'
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
                v-tooltip="ctrans('Open advanced settings')"
                @click="isSectionVisible = !isSectionVisible"
                class="cursor-pointer p-2 rounded border flex items-center justify-center flex-shrink-0 self-start sm:self-auto"
                :class="isSectionVisible ? 'dashboard-accent-soft border-transparent' : 'border-gray-300 text-gray-400 hover:bg-gray-200'">
                <FontAwesomeIcon icon="far fa-cog" fixed-width aria-hidden="true" class="text-xl sm:text-2xl" />
            </div>
        </div>

        <transition name="slide-to-right">
            <div v-show="isSectionVisible" id="dashboard-settings" class="flex flex-wrap items-center gap-3 mb-2 text-sm">

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Toggle: model_state -->
                    <Transition name="slide-to-right">
                        <div v-if="settings.model_state_type && currentTab === 'shops'" class="flex items-center gap-x-2 sm:gap-x-4 flex-shrink-0">
                            <p v-tooltip="settings.model_state_type.options[0].tooltip" class="leading-none whitespace-nowrap" :class="[ settings.model_state_type.options[0].value === settings.model_state_type.value ? 'font-semibold dashboard-accent-text underline' : 'opacity-50', ]">
                                {{ settings.model_state_type.options[0].label }}
                            </p>
                            <ToggleSwitch
                                :modelValue="settings.model_state_type.value"
                                @update:modelValue="(value: any) => updateToggle(settings.model_state_type.id, value, `left_model_state_type`, false)"
                                :falseValue="settings.model_state_type.options[0].value"
                                :trueValue="settings.model_state_type.options[1]?.value"
                                :disabled="`left_model_state_type` === isLoadingToggle"
                            />
                            <p v-tooltip="settings.model_state_type.options[1]?.tooltip" class="whitespace-nowrap" :class="[ settings.model_state_type.options[1]?.value === settings.model_state_type.value ? 'font-semibold dashboard-accent-text underline' : 'opacity-50', ]">
                                {{ settings.model_state_type.options[1]?.label }}
                            </p>
                        </div>
                    </Transition>

                    <!-- Toggle: partners_type (external, all) -->
                    <DashboardSettingToggle
                        v-if="settings.partners_type"
                        :label="ctrans('Include partners')"
                        :tooltip="settings.partners_type.options[1]?.tooltip"
                        :isOn="settings.partners_type.value === settings.partners_type.options[1]?.value"
                        :isLoading="isLoadingToggle === 'left_partners_type'"
                        @change="(isOn) => updatePartnersType(settings.partners_type.options[isOn ? 1 : 0].value)"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Toggle: data_display_type (minified, full) -->
                    <DashboardSettingChoice
                        v-if="settings.data_display_type"
                        :value="settings.data_display_type.value"
                        :options="settings.data_display_type.options"
                        @change="updateDataDisplayType"
                    />

                    <!-- Selector: top_customers_limit (only on top_customers tab) -->
                    <div v-if="settings.top_customers_limit && currentTab === 'top_customers'" class="flex items-center gap-x-2 sm:gap-x-4 flex-shrink-0">
                        <p class="whitespace-nowrap leading-none">{{ ctrans('Show top') }}</p>
                        <RadioGroup class="relative"
                                    :modelValue="settings.top_customers_limit.value"
                                    @update:modelValue="(value: any) => updateTopCustomersLimit(Number(value))"
                        >
                            <div v-if="`top_customers_limit` === isLoadingToggle" class="absolute inset-0 bg-black/50 rounded-md flex items-center justify-center z-10">
                                <LoadingIcon class="text-white text-xl m-auto" />
                            </div>
                            <RadioGroupLabel class="sr-only">{{ ctrans('Top customers limit') }}</RadioGroupLabel>
                            <div class="flex border border-gray-300 rounded-md overflow-hidden w-fit">
                                <RadioGroupOption
                                    as="template" v-for="option in settings.top_customers_limit.options"
                                    :key="option.value"
                                    :value="option.value"
                                    v-slot="{ checked }"
                                >
                                    <div :class="[
                                            'cursor-pointer focus:outline-none flex items-center justify-center py-1 sm:py-2 md:py-3 px-2 sm:px-3 text-xs sm:text-sm font-medium whitespace-nowrap',
                                            checked ? 'dashboard-accent' : 'bg-white text-gray-700 hover:bg-gray-200',
                                        ]"
                                         v-tooltip="option.tooltip"
                                    >
                                        <RadioGroupLabel as="span">{{ option.label }}</RadioGroupLabel>
                                    </div>
                                </RadioGroupOption>
                            </div>
                        </RadioGroup>
                    </div>

                    <!-- Toggle: currency_type -->
                    <DashboardSettingChoice
                        v-if="settings.currency_type && settings.currency_type.display !== false"
                        :value="settings.currency_type.value"
                        :options="settings.currency_type.options"
                        @change="updateCurrencyType"
                    />

                    <div class="flex items-center gap-x-2 flex-shrink-0">
                        <button type="button" @click="refreshDashboard" :disabled="isRefreshing"
                            v-tooltip="ctrans('Recalculate the dashboard numbers now')"
                            class="flex items-center gap-x-2 rounded border border-gray-300 px-3 h-9 font-medium text-gray-600 hover:bg-gray-200 whitespace-nowrap">
                            <FontAwesomeIcon icon="far fa-sync-alt" :class="isRefreshing ? 'animate-spin' : ''" fixed-width aria-hidden="true" />
                            {{ ctrans('Refresh') }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.dashboard-accent {
    background-color: v-bind(accentColor);
    color: v-bind(accentTextColor);
}

.dashboard-accent-soft {
    background-color: v-bind("`color-mix(in srgb, ${accentColor} 18%, transparent)`");
    color: v-bind("`color-mix(in srgb, ${accentColor} 75%, black)`");
}

.dashboard-accent-text {
    color: v-bind("`color-mix(in srgb, ${accentColor} 75%, black)`");
}

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
