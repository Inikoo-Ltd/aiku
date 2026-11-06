<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 06 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputNumber from "primevue/inputnumber"
import ToggleSwitch from "primevue/toggleswitch"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faTrashAlt } from "@fal"
import { ctrans } from "@/Composables/useTrans"

export type CrawlSchedule = {
    website_id: number
    domain: string
    frequency: "daily" | "weekly" | "monthly"
    weekday: number | null
    day_of_month: number | null
    hour: number
    is_active: boolean
    next_run_at: string | null
    last_run_at: string | null
    user_name: string | null
    can_edit: boolean
}

export type ScheduleSettings = {
    frequencies: Record<string, string>
    saveRoute: { name: string, method: string }
    deleteRoute: { name: string, method: string }
}

const props = defineProps<{
    settings: ScheduleSettings
    websites: Array<{ id: number, domain: string }>
    schedule: CrawlSchedule | null
    websiteId?: number | null
}>()

const visible = defineModel<boolean>("visible", { default: false })

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"
const toggleClass = "[&.p-toggleswitch-checked_.p-toggleswitch-slider]:!bg-[var(--app-accent)]"

const websiteId = ref<number | null>(null)
const frequency = ref<"daily" | "weekly" | "monthly">("weekly")
const weekday = ref(1)
const dayOfMonth = ref(1)
const hour = ref(3)
const isActive = ref(true)
const errors = ref<Record<string, string>>({})
const isSaving = ref(false)
const isDeleting = ref(false)

watch(visible, (isVisible) => {
    if (!isVisible) {
        return
    }

    websiteId.value = props.schedule?.website_id ?? props.websiteId ?? (props.websites.length === 1 ? props.websites[0].id : null)
    frequency.value = props.schedule?.frequency ?? "weekly"
    weekday.value = props.schedule?.weekday ?? 1
    dayOfMonth.value = props.schedule?.day_of_month ?? 1
    hour.value = props.schedule?.hour ?? 3
    isActive.value = props.schedule?.is_active ?? true
    errors.value = {}
}, { immediate: true })

const frequencyOptions = computed(() => Object.entries(props.settings.frequencies).map(([value, label]) => ({ value: value as "daily" | "weekly" | "monthly", label })))

const weekdayOptions = [
    { value: 0, label: ctrans("Sunday") },
    { value: 1, label: ctrans("Monday") },
    { value: 2, label: ctrans("Tuesday") },
    { value: 3, label: ctrans("Wednesday") },
    { value: 4, label: ctrans("Thursday") },
    { value: 5, label: ctrans("Friday") },
    { value: 6, label: ctrans("Saturday") },
]

const hourOptions = Array.from({ length: 24 }, (_, value) => ({ value, label: `${String(value).padStart(2, "0")}:00 UTC` }))

const submit = () => {
    if (!websiteId.value) {
        errors.value = { website: ctrans("Choose the website to schedule") }

        return
    }

    router.post(route(props.settings.saveRoute.name, [websiteId.value]), {
        frequency: frequency.value,
        weekday: frequency.value === "weekly" ? weekday.value : null,
        day_of_month: frequency.value === "monthly" ? dayOfMonth.value : null,
        hour: hour.value,
        is_active: isActive.value,
    }, {
        preserveScroll: true,
        onStart: () => {
            isSaving.value = true
            errors.value = {}
        },
        onError: (responseErrors) => errors.value = responseErrors,
        onSuccess: () => visible.value = false,
        onFinish: () => isSaving.value = false,
    })
}

const removeSchedule = () => {
    if (!props.schedule) {
        return
    }

    router.delete(route(props.settings.deleteRoute.name, [props.schedule.website_id]), {
        preserveScroll: true,
        onStart: () => {
            isDeleting.value = true
            errors.value = {}
        },
        onError: () => errors.value = { delete: ctrans("The schedule could not be removed. Try again in a moment.") },
        onSuccess: () => visible.value = false,
        onFinish: () => isDeleting.value = false,
    })
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="schedule ? ctrans('Crawl schedule of :website', { website: schedule.domain }) : ctrans('Schedule a crawl')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-4 text-sm">
            <div v-if="!schedule" class="space-y-1">
                <label for="schedule-website" class="block text-xs font-medium text-gray-700">{{ ctrans("Website") }}</label>
                <Select v-model="websiteId" inputId="schedule-website" :options="websites" optionLabel="domain" optionValue="id" filter :placeholder="ctrans('Choose a website')" :class="fieldFocusClass" fluid />
                <p v-if="errors.website" class="text-xs text-red-600">{{ errors.website }}</p>
            </div>

            <div class="space-y-1">
                <SegmentedToggle v-model="frequency" :options="frequencyOptions" :ariaLabel="ctrans('How often')" />
                <p v-if="errors.frequency" class="text-xs text-red-600">{{ errors.frequency }}</p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div v-if="frequency === 'weekly'" class="space-y-1">
                    <label for="schedule-weekday" class="block text-xs font-medium text-gray-700">{{ ctrans("Day") }}</label>
                    <Select v-model="weekday" inputId="schedule-weekday" :options="weekdayOptions" optionLabel="label" optionValue="value" :class="fieldFocusClass" fluid />
                    <p v-if="errors.weekday" class="text-xs text-red-600">{{ errors.weekday }}</p>
                </div>
                <div v-if="frequency === 'monthly'" class="space-y-1">
                    <label for="schedule-day" class="block text-xs font-medium text-gray-700">{{ ctrans("Day of the month") }}</label>
                    <InputNumber v-model="dayOfMonth" inputId="schedule-day" :min="1" :max="31" :useGrouping="false" :class="fieldFocusClass" fluid />
                    <p v-if="errors.day_of_month" class="text-xs text-red-600">{{ errors.day_of_month }}</p>
                </div>
                <div class="space-y-1">
                    <label for="schedule-hour" class="block text-xs font-medium text-gray-700">{{ ctrans("Time") }}</label>
                    <Select v-model="hour" inputId="schedule-hour" :options="hourOptions" optionLabel="label" optionValue="value" :class="fieldFocusClass" fluid />
                    <p v-if="errors.hour" class="text-xs text-red-600">{{ errors.hour }}</p>
                </div>
            </div>

            <p v-if="frequency === 'monthly' && dayOfMonth > 28" class="text-xs text-gray-600">{{ ctrans("In months without this day, the crawl runs on the last day of the month.") }}</p>

            <label class="flex cursor-pointer items-center gap-2">
                <ToggleSwitch v-model="isActive" :class="toggleClass" />
                <span>{{ ctrans("Active") }}</span>
            </label>

            <p class="text-xs text-gray-600">{{ ctrans("Scheduled crawls check the whole website, so they also update the health score and page scores. The weekly crawl of every website on Sunday still runs.") }}</p>

            <div v-if="schedule" class="border-t border-gray-100 pt-3">
                <Button type="tertiary" size="xs" :label="ctrans('Remove schedule')" :loading="isDeleting" @click="removeSchedule">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTrashAlt" class="text-amber-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <p v-if="errors.delete" class="mt-1 text-xs text-red-600">{{ errors.delete }}</p>
            </div>
        </div>

        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="ctrans('Save schedule')" :loading="isSaving" @click="submit">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
