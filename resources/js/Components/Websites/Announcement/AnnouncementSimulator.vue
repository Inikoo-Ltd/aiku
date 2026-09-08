<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import VueDatePicker from '@vuepic/vue-datepicker'
import { debounce } from 'lodash-es'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Tag from '@/Components/Tag.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { ctrans } from '@/Composables/useTrans'
import { useFormatTime } from '@/Composables/useFormatTime'
import { getAnnouncementComponent } from '@/Composables/useAnnouncement'
import {
    isAnnouncementVisible,
    isPageRuleMatch,
    isPauseOver,
    isShownOnPage,
    normalizePagePath,
    type AnnouncementVisibilityData,
} from '@/Iris/Composables/useAnnouncementVisibility'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faClock, faInfoCircle, faUserCircle, faUserSlash } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faClock, faInfoCircle, faUserCircle, faUserSlash)

interface SimulatedAnnouncement extends AnnouncementVisibilityData {
    ulid?: string
    name: string
    show_pages: string[]
    hide_pages: string[]
    container_properties: {}
    fields: {}
    template_code: string | null
}

const positions = [
    { key: 'top-bar', label: ctrans('Top bar') },
    { key: 'bottom-menu', label: ctrans('Below the Menu') },
    { key: 'top-footer', label: ctrans('Above the Footer') },
]

const simulatedDate = ref<Date>(new Date())
const simulatedPage = ref('')
const visitorType = ref<'logged_out' | 'logged_in'>('logged_out')
const announcementList = ref<SimulatedAnnouncement[]>([])
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

const fetchAnnouncements = async () => {
    isLoading.value = true
    errorMessage.value = null

    try {
        const response = await axios.get(
            route('grp.json.announcement_simulation.index', { website: route().params.website })
        )
        announcementList.value = response.data ?? []
    } catch (error) {
        errorMessage.value = ctrans('Failed to load the announcements, change the time to retry')
    } finally {
        isLoading.value = false
    }
}

const debouncedFetch = debounce(fetchAnnouncements, 350)

const onChangeSimulatedDate = (date: Date) => {
    simulatedDate.value = date
    debouncedFetch()
}

const resetToNow = () => {
    onChangeSimulatedDate(new Date())
}

const announcementUrl = (announcement: SimulatedAnnouncement): string | null => {
    if (!announcement.ulid) {
        return null
    }

    return route('grp.org.shops.show.web.announcements.show', {
        ...route().params,
        announcement: announcement.ulid,
    })
}

const simulatedMs = computed(() => simulatedDate.value.getTime())
const isSimulatedVisitorLoggedIn = computed(() => visitorType.value === 'logged_in')

const isSimulatedPageCustomerPortal = computed(() => {
    const normalizedPage = normalizePagePath(simulatedPage.value)

    return normalizedPage === 'app' || normalizedPage.startsWith('app/')
})

const hiddenReason = (announcement: SimulatedAnnouncement, shown: SimulatedAnnouncement | null): string => {
    if (announcement.schedule_at && simulatedMs.value < new Date(announcement.schedule_at).getTime()) {
        return ctrans('Not started yet')
    }

    if (announcement.schedule_finish_at && simulatedMs.value >= new Date(announcement.schedule_finish_at).getTime()) {
        return ctrans('Already finished')
    }

    if (!isPauseOver(announcement, simulatedMs.value)) {
        return ctrans('Paused until :date', {
            date: useFormatTime(announcement.resumes_at, { formatTime: 'hm' }),
        })
    }

    if (!isShownOnPage(announcement, simulatedPage.value)) {
        const isHiddenByRule = (announcement.hide_pages ?? []).some(
            (rule) => isPageRuleMatch(rule, simulatedPage.value)
        )

        return isHiddenByRule
            ? ctrans('Hidden on this page')
            : ctrans('Not targeted to this page')
    }

    const authState = announcement.settings?.target_users?.auth_state
    if (authState === 'logged_in' && !isSimulatedVisitorLoggedIn.value) {
        return ctrans('Only for logged in visitors')
    }
    if (authState === 'logged_out' && isSimulatedVisitorLoggedIn.value) {
        return ctrans('Only for guest visitors')
    }
    if (!authState) {
        return ctrans('No target visitors set')
    }

    if (shown) {
        return ctrans('Covered by :name', { name: shown.name })
    }

    return ctrans('Hidden')
}

const simulationResults = computed(() => {
    return positions.map((position) => {
        const candidates = announcementList.value.filter(
            (announcement) => announcement?.settings?.position === position.key
        )

        const shown = candidates.find((announcement) =>
            isAnnouncementVisible(announcement, simulatedMs.value, isSimulatedVisitorLoggedIn.value, simulatedPage.value)
        ) ?? null

        const hidden = candidates
            .filter((announcement) => announcement !== shown)
            .map((announcement) => ({
                name: announcement.name,
                url: announcementUrl(announcement),
                reason: hiddenReason(announcement, shown),
            }))

        return { ...position, announcement: shown, hidden }
    })
})

onMounted(fetchAnnouncements)

onBeforeUnmount(() => {
    debouncedFetch.cancel()
})
</script>

<template>
    <div class="mt-5 rounded-xl border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200">
            <div class="font-semibold flex items-center gap-x-2">
                <FontAwesomeIcon icon="fal fa-clock" fixed-width aria-hidden="true" />
                {{ ctrans('Announcement simulator') }}
                <LoadingIcon v-if="isLoading" class="text-gray-400" />
            </div>
            <p class="text-xs text-gray-500">
                {{ ctrans('Pick a moment to see which announcement the website will show at that time') }}
            </p>
        </div>

        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex flex-wrap items-end gap-x-8 gap-y-3">
            <div>
                <div class="mb-1 text-xs font-medium text-gray-500">
                    {{ ctrans('Simulated time') }}
                </div>
                <div class="flex items-center gap-x-1.5">
                    <VueDatePicker
                        :modelValue="simulatedDate"
                        @update:modelValue="onChangeSimulatedDate"
                        time-picker-inline
                        auto-apply
                        :clearable="false"
                        class="w-fit"
                    >
                        <template #trigger>
                            <Button :style="'secondary'" size="xs" icon="fal fa-clock">
                                {{ useFormatTime(simulatedDate, { formatTime: 'hm' }) }}
                            </Button>
                        </template>
                    </VueDatePicker>

                    <Button :style="'tertiary'" size="xs" :label="ctrans('Now')" @click="resetToNow" />
                </div>
            </div>

            <div>
                <div class="mb-1 text-xs font-medium text-gray-500">
                    {{ ctrans('Page') }}
                </div>
                <div class="flex items-center rounded-md border border-gray-300 bg-white text-xs focus-within:border-indigo-400">
                    <span class="pl-2 text-gray-400 select-none">/</span>
                    <input
                        v-model="simulatedPage"
                        type="text"
                        :placeholder="ctrans('homepage')"
                        class="w-44 border-0 bg-transparent px-1 py-1.5 text-xs focus:outline-none focus:ring-0"
                    />
                </div>
            </div>

            <div>
                <div class="mb-1 text-xs font-medium text-gray-500">
                    {{ ctrans('Visitor') }}
                </div>
                <div class="flex rounded-md border border-gray-300 overflow-hidden text-xs bg-white">
                    <button
                        type="button"
                        class="px-2.5 py-1.5 flex items-center gap-x-1.5"
                        :class="visitorType === 'logged_out' ? 'bg-[color-mix(in_srgb,var(--theme-color-4)_14%,transparent)] text-[color-mix(in_srgb,var(--theme-color-4)_50%,black)]' : 'bg-white text-gray-500 hover:bg-gray-50'"
                        @click="visitorType = 'logged_out'"
                    >
                        <FontAwesomeIcon icon="fal fa-user-slash" fixed-width aria-hidden="true" />
                        {{ ctrans('On logged out') }}
                    </button>
                    <button
                        type="button"
                        class="px-2.5 py-1.5 flex items-center gap-x-1.5 border-l border-gray-300"
                        :class="visitorType === 'logged_in' ? 'bg-[color-mix(in_srgb,var(--theme-color-4)_14%,transparent)] text-[color-mix(in_srgb,var(--theme-color-4)_50%,black)]' : 'bg-white text-gray-500 hover:bg-gray-50'"
                        @click="visitorType = 'logged_in'"
                    >
                        <FontAwesomeIcon icon="fal fa-user-circle" fixed-width aria-hidden="true" />
                        {{ ctrans('On logged in') }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="errorMessage" class="px-4 py-3 text-sm text-red-500">
            {{ errorMessage }}
        </div>

        <div v-else-if="isSimulatedPageCustomerPortal" class="px-4 py-3 flex items-start gap-x-2 text-sm text-gray-500">
            <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" class="mt-0.5" />
            <span>
                {{ ctrans('Pages under /app are the customer portal (login, account), the website never shows announcements there') }}
            </span>
        </div>

        <div v-else class="divide-y divide-gray-100">
            <div v-for="result in simulationResults" :key="result.key" class="px-4 py-3 space-y-1.5">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                    {{ result.label }}
                </div>

                <template v-if="result.announcement">
                    <div v-if="result.announcement.template_code" class="rounded-md overflow-hidden border border-gray-300 shadow-sm">
                        <component
                            :is="getAnnouncementComponent(result.announcement.template_code)"
                            :announcementData="result.announcement"
                            :key="`${result.key}-${result.announcement.name}`"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-600">
                        <Link
                            v-if="announcementUrl(result.announcement)"
                            :href="announcementUrl(result.announcement)"
                            class="primaryLink font-semibold"
                        >
                            {{ result.announcement.name }}
                        </Link>
                        <span v-else class="font-semibold">{{ result.announcement.name }}</span>
                        <span v-if="result.announcement.schedule_at">
                            {{ ctrans('from') }} {{ useFormatTime(result.announcement.schedule_at, { formatTime: 'hm' }) }}
                        </span>
                        <span v-if="result.announcement.schedule_finish_at">
                            {{ ctrans('until') }} {{ useFormatTime(result.announcement.schedule_finish_at, { formatTime: 'hm' }) }}
                        </span>
                        <template v-for="page in result.announcement.show_pages" :key="`show-${page}`">
                            <Tag :label="page" noHoverColor />
                        </template>
                    </div>
                </template>

                <div v-else class="text-sm italic text-gray-400">
                    {{ ctrans('No announcement will be shown') }}
                </div>

                <div v-if="result.hidden.length" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                    <span
                        v-for="hiddenAnnouncement in result.hidden"
                        :key="hiddenAnnouncement.name"
                        v-tooltip="hiddenAnnouncement.reason"
                    >
                        <Link
                            v-if="hiddenAnnouncement.url"
                            :href="hiddenAnnouncement.url"
                            class="secondaryLink"
                        >
                            {{ hiddenAnnouncement.name }}
                        </Link>
                        <template v-else>{{ hiddenAnnouncement.name }}</template>
                        : {{ hiddenAnnouncement.reason }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
