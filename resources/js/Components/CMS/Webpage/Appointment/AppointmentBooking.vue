<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue"
import DatePicker from "primevue/datepicker"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import Checkbox from "primevue/checkbox"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faStoreAlt, faVideo, faClock, faMapMarkerAlt, faCalendarAlt, faGlobe, faArrowLeft } from "@fal"
import { faCheckCircle } from "@fas"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"

library.add(faStoreAlt, faVideo, faClock, faMapMarkerAlt, faCalendarAlt, faGlobe, faArrowLeft, faCheckCircle)

export type AppointmentTypeOption = {
    id: number
    name: string
    description?: string | null
    meeting_mode: "store_visit" | "video_call"
    meeting_mode_label: string
    location?: string | null
    duration_minutes: number
    slots: Record<string, string[]>
}

export type AppointmentBookingPayload = {
    appointment_type_id: number
    date: string
    time: string
    contact_name: string
    email: string
    phone: string
    number_visitors: number
    notes: string
    marketing_opt_in: boolean
    website_url: string
}

export type AppointmentBookingResult = {
    appointment_type: string
    starts_at: string
    ends_at: string
    location?: string | null
}

const props = defineProps<{
    appointmentTypes: AppointmentTypeOption[]
    organizerName?: string
    showPickerHeading?: boolean
    timezone?: string
    isLoading?: boolean
    showMarketingOptIn?: boolean
    prefill?: { contact_name?: string, email?: string }
    book: (payload: AppointmentBookingPayload) => Promise<AppointmentBookingResult>
}>()

const selectedTypeId = ref<number | null>(null)
const selectedDate = ref<Date | null>(null)
const selectedTime = ref<string | null>(null)
const step = ref<"pick" | "details">("pick")
const isSubmitting = ref(false)
const errors = ref<Record<string, string>>({})
const result = ref<AppointmentBookingResult | null>(null)

const form = reactive({
    contact_name: props.prefill?.contact_name ?? "",
    email: props.prefill?.email ?? "",
    phone: "",
    number_visitors: 1,
    notes: "",
    marketing_opt_in: false,
    website_url: "",
})

watch(() => props.appointmentTypes, (types) => {
    if (types.length === 1) {
        selectedTypeId.value = types[0].id
    }
}, { immediate: true })

const selectedType = computed(() => props.appointmentTypes.find(type => type.id === selectedTypeId.value) ?? null)

const toIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}

const availableDates = computed(() => Object.keys(selectedType.value?.slots ?? {}).sort())
const availableDateSet = computed(() => new Set(availableDates.value))
const today = new Date()
today.setHours(0, 0, 0, 0)
const maxDate = computed(() => (availableDates.value.length ? fromIsoDate(availableDates.value[availableDates.value.length - 1]) : today))
const disabledDates = computed(() => {
    const disabled: Date[] = []
    for (const day = new Date(today); day <= maxDate.value; day.setDate(day.getDate() + 1)) {
        if (!availableDateSet.value.has(toIsoDate(day))) {
            disabled.push(new Date(day))
        }
    }
    return disabled
})

const timesForDate = computed(() => (selectedDate.value ? selectedType.value?.slots[toIsoDate(selectedDate.value)] ?? [] : []))

const selectType = (typeId: number) => {
    selectedTypeId.value = typeId
    selectedDate.value = null
    selectedTime.value = null
    step.value = "pick"
}

watch(selectedDate, () => {
    selectedTime.value = null
})

const longDate = (date: Date) => date.toLocaleDateString(undefined, { weekday: "long", day: "numeric", month: "long", year: "numeric" })
const dayHeading = (date: Date) => date.toLocaleDateString(undefined, { weekday: "long", month: "long", day: "numeric" })

const formatDuration = (minutes: number): string => {
    const hours = Math.floor(minutes / 60)
    const rest = minutes % 60
    if (!hours) {
        return ctrans(":minutes min", { minutes: rest })
    }
    return rest ? ctrans(":hours hr :minutes min", { hours, minutes: rest }) : ctrans(":hours hr", { hours })
}

const clock = ref(new Date())
const clockTimer = setInterval(() => (clock.value = new Date()), 30000)
onBeforeUnmount(() => clearInterval(clockTimer))
const timezoneNow = computed(() => {
    try {
        return clock.value.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit", timeZone: props.timezone })
    } catch {
        return ""
    }
})
const endTime = (time: string, minutes: number) => {
    const [hours, mins] = time.split(":").map(Number)
    const total = hours * 60 + mins + minutes
    return `${String(Math.floor(total / 60) % 24).padStart(2, "0")}:${String(total % 60).padStart(2, "0")}`
}
const resultDate = computed(() => (result.value ? longDate(fromIsoDate(result.value.starts_at.split(" ")[0])) : ""))

const submit = async () => {
    if (!selectedType.value || !selectedDate.value || !selectedTime.value) {
        return
    }
    isSubmitting.value = true
    errors.value = {}
    try {
        result.value = await props.book({
            appointment_type_id: selectedType.value.id,
            date: toIsoDate(selectedDate.value),
            time: selectedTime.value,
            ...form,
        })
    } catch (error: any) {
        const status = error?.response?.status
        const serverErrors = error?.response?.data?.errors ?? {}
        errors.value = Object.fromEntries(Object.entries(serverErrors).map(([key, messages]) => [key, Array.isArray(messages) ? messages[0] : String(messages)]))
        if (status === 429) {
            errors.value.general = ctrans("Too many attempts. Please wait a minute and try again.")
        } else {
            const fieldsWithMessage = ["contact_name", "email", "phone", "time"]
            const otherMessages = Object.entries(errors.value).filter(([key]) => !fieldsWithMessage.includes(key)).map(([, message]) => message)
            if (otherMessages.length || !Object.keys(errors.value).length) {
                errors.value.general = otherMessages[0] ?? ctrans("Something went wrong. Please try again.")
            }
        }
        if (errors.value.time) {
            step.value = "pick"
            selectedTime.value = null
        }
    }
    isSubmitting.value = false
}

const fieldPt = { root: { class: "w-full" } }
const datePickerDt = {
    panel: { background: "transparent", borderColor: "transparent", shadow: "none", padding: "0" },
    header: { background: "transparent", borderColor: "transparent", padding: "0 0 0.5rem 0" },
    title: { gap: "0.35rem", fontWeight: "600" },
    dayView: { margin: "0" },
    weekDay: { padding: "0.5rem 0", fontWeight: "500", color: "#334155" },
    date: {
        width: "2.75rem",
        height: "2.75rem",
        padding: "0",
        borderRadius: "50%",
        color: "#334155",
        selectedBackground: "var(--theme-color-0)",
        selectedColor: "var(--theme-color-1)",
        hoverBackground: "color-mix(in srgb, var(--theme-color-0) 20%, white)",
        hoverColor: "var(--theme-color-0)",
    },
    colorScheme: {
        light: {
            today: { background: "transparent", color: "inherit" },
        },
    },
}

type DayContext = { disabled: boolean, selected: boolean, today: boolean }

const datePickerPt = {
    root: { class: "w-full" },
    panel: { class: "!block w-full !overflow-visible" },
    header: { class: "!justify-center !gap-6 !border-b-0" },
    title: { class: "!flex-none" },
    selectMonth: { class: "!p-0 !text-sm !font-semibold !text-slate-800 hover:!bg-transparent" },
    selectYear: { class: "!p-0 !text-sm !font-semibold !text-slate-800 hover:!bg-transparent" },
    pcPrevButton: { root: { class: "!text-slate-500 disabled:!text-slate-300" } },
    pcNextButton: { root: { class: "!text-[var(--theme-color-0)] disabled:!text-slate-300" } },
    dayView: { class: "!my-0 !w-full !table-fixed !border-separate !border-0" },
    tableHeaderRow: { class: "!bg-transparent" },
    tableHeaderCell: { class: "!border-0 !bg-transparent !p-0 !pb-2 !text-center !font-normal" },
    weekDay: { class: "!text-xs !text-slate-700" },
    dayCell: { class: "!border-0 !bg-transparent !p-0.5 !text-center" },
    day: ({ context }: { context: DayContext }) => ({
        class: [
            "relative mx-auto aspect-square !h-auto !w-full !max-w-[2.75rem] text-[0.95rem] max-sm:text-sm",
            context.disabled ? "!opacity-100 !text-slate-700" : "",
            context.selected ? "!shadow-none !outline-none !font-bold" : "",
            !context.disabled && !context.selected
                ? "!font-semibold !text-[var(--theme-color-0)] !bg-[color-mix(in_srgb,var(--theme-color-0)_9%,white)] hover:!bg-[color-mix(in_srgb,var(--theme-color-0)_20%,white)]"
                : "",
            context.today
                ? "after:absolute after:bottom-1.5 after:h-1 after:w-1 after:rounded-full after:bg-current after:content-['']"
                : "",
        ],
    }),
}
</script>

<template>
    <div class="w-full text-slate-700">
        <div v-if="isLoading" class="flex justify-center py-16 text-2xl text-slate-400">
            <LoadingIcon />
        </div>

        <div v-else-if="!appointmentTypes.length" class="mx-auto max-w-2xl rounded-lg border border-dashed border-slate-300 p-6 text-center text-slate-500">
            {{ ctrans("Booking is not open at the moment. Please contact us.") }}
        </div>

        <div v-else-if="result" class="mx-auto max-w-2xl rounded-lg border border-slate-200 bg-white px-6 py-12 text-center shadow-[0_1px_8px_rgba(0,0,0,0.08)]">
            <FontAwesomeIcon icon="fas fa-check-circle" class="text-5xl text-green-500" fixed-width aria-hidden="true" />
            <h3 class="mt-4 text-xl font-bold text-slate-800">{{ ctrans("Your request is sent") }}</h3>
            <p class="mt-1 text-slate-500">{{ ctrans("We will confirm your appointment shortly.") }}</p>
            <div class="mx-auto mt-6 max-w-sm space-y-3 rounded-lg border border-slate-200 p-5 text-left text-sm font-semibold text-slate-600">
                <div class="text-base font-bold text-slate-800">{{ result.appointment_type }}</div>
                <div class="flex gap-3"><FontAwesomeIcon icon="fal fa-calendar-alt" fixed-width aria-hidden="true" class="mt-0.5 text-lg" />{{ result.starts_at.split(" ")[1] }}–{{ result.ends_at }}, {{ resultDate }}</div>
                <div v-if="timezone" class="flex gap-3"><FontAwesomeIcon icon="fal fa-globe" fixed-width aria-hidden="true" class="mt-0.5 text-lg" />{{ timezone }}</div>
                <div v-if="result.location" class="flex gap-3"><FontAwesomeIcon icon="fal fa-map-marker-alt" fixed-width aria-hidden="true" class="mt-0.5 text-lg" />{{ result.location }}</div>
            </div>
        </div>

        <div v-else-if="!selectedType" class="mx-auto max-w-4xl">
            <div v-if="showPickerHeading" class="mb-6 text-center">
                <div v-if="organizerName" class="text-sm font-semibold text-slate-500">{{ organizerName }}</div>
                <h3 class="mt-1 text-2xl font-bold text-slate-800">{{ ctrans("Choose a visit") }}</h3>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <button
                    v-for="type in appointmentTypes" :key="type.id" type="button"
                    class="group flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white text-left shadow-[0_1px_8px_rgba(0,0,0,0.08)] transition hover:-translate-y-0.5 hover:border-[var(--theme-color-0)] hover:shadow-[0_4px_16px_rgba(0,0,0,0.12)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--theme-color-0)]"
                    @click="selectType(type.id)">
                    <span class="h-1.5 w-full bg-[var(--theme-color-0)]" aria-hidden="true" />
                    <span class="flex flex-1 flex-col p-6">
                        <span class="flex items-center gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--theme-color-0)_12%,white)] text-lg text-[var(--theme-color-0)]">
                                <FontAwesomeIcon :icon="type.meeting_mode === 'video_call' ? 'fal fa-video' : 'fal fa-store-alt'" fixed-width aria-hidden="true" />
                            </span>
                            <span class="text-lg font-bold leading-tight text-slate-800">{{ type.name }}</span>
                        </span>
                        <span class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm font-semibold text-slate-500">
                            <span><FontAwesomeIcon icon="fal fa-clock" fixed-width aria-hidden="true" /> {{ formatDuration(type.duration_minutes) }}</span>
                            <span>{{ type.meeting_mode_label }}</span>
                        </span>
                        <span v-if="type.location" class="mt-2 flex gap-1 text-sm text-slate-500">
                            <FontAwesomeIcon icon="fal fa-map-marker-alt" fixed-width aria-hidden="true" class="mt-0.5" />
                            <span class="line-clamp-2">{{ type.location }}</span>
                        </span>
                        <span v-if="type.description" class="mt-3 line-clamp-3 text-sm leading-relaxed text-slate-600">{{ type.description }}</span>
                        <span class="mt-auto pt-5 text-sm font-bold text-[var(--theme-color-0)]">
                            {{ ctrans("Select") }} <span class="inline-block transition group-hover:translate-x-1" aria-hidden="true">→</span>
                        </span>
                    </span>
                </button>
            </div>
        </div>

        <div
            v-else
            class="mx-auto flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-[0_1px_8px_rgba(0,0,0,0.08)] transition-[max-width] duration-300 md:flex-row"
            :class="step === 'pick' && selectedDate ? 'max-w-[1060px]' : 'max-w-[760px]'">
            <aside
                class="border-b border-slate-200 px-6 py-6 md:border-b-0 md:border-r md:px-6 md:py-7"
                :class="step === 'pick' && selectedDate ? 'md:w-[300px] md:shrink-0' : 'md:w-1/2'">
                <button
                    v-if="step === 'details' || appointmentTypes.length > 1" type="button"
                    class="mb-5 flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 text-xl text-[var(--theme-color-0)] transition hover:bg-slate-50"
                    :aria-label="ctrans('Back')"
                    @click="step === 'details' ? (step = 'pick') : (selectedTypeId = null)">
                    <FontAwesomeIcon icon="fal fa-arrow-left" fixed-width aria-hidden="true" />
                </button>
                <div v-if="organizerName" class="text-sm font-semibold text-slate-500">{{ organizerName }}</div>
                <h3 class="mt-1 text-[1.5rem] font-bold leading-tight text-slate-800">{{ selectedType.name }}</h3>
                <div class="mt-6 space-y-4 text-sm font-semibold text-slate-500">
                    <div class="flex items-start gap-3">
                        <FontAwesomeIcon icon="fal fa-clock" fixed-width aria-hidden="true" class="mt-0.5 text-lg text-slate-600" />
                        <span>{{ formatDuration(selectedType.duration_minutes) }}</span>
                    </div>
                    <div v-if="selectedType.meeting_mode === 'video_call'" class="flex items-start gap-3">
                        <FontAwesomeIcon icon="fal fa-video" fixed-width aria-hidden="true" class="mt-0.5 text-lg text-slate-600" />
                        <span>{{ ctrans("Video call on WhatsApp") }}</span>
                    </div>
                    <div v-if="selectedType.location" class="flex items-start gap-3">
                        <FontAwesomeIcon icon="fal fa-map-marker-alt" fixed-width aria-hidden="true" class="mt-0.5 text-lg text-slate-600" />
                        <span>{{ selectedType.location }}</span>
                    </div>
                    <template v-if="step === 'details' && selectedDate && selectedTime">
                        <div class="flex items-start gap-3">
                            <FontAwesomeIcon icon="fal fa-calendar-alt" fixed-width aria-hidden="true" class="mt-0.5 text-lg text-slate-600" />
                            <span>{{ selectedTime }}–{{ endTime(selectedTime, selectedType.duration_minutes) }}, {{ longDate(selectedDate) }}</span>
                        </div>
                        <div v-if="timezone" class="flex items-start gap-3">
                            <FontAwesomeIcon icon="fal fa-globe" fixed-width aria-hidden="true" class="mt-0.5 text-lg text-slate-600" />
                            <span>{{ timezone }}</span>
                        </div>
                    </template>
                </div>
                <div v-if="selectedType.description" class="mt-6 text-sm leading-relaxed text-slate-600">{{ selectedType.description }}</div>
            </aside>

            <section v-if="step === 'pick'" class="flex flex-1 flex-col gap-6 px-6 py-7 lg:flex-row">
                <div class="min-w-0 flex-1">
                    <h3 class="mb-6 text-xl font-bold text-slate-800">{{ ctrans("Select a Date & Time") }}</h3>

                    <div v-if="!availableDates.length" class="py-10 text-center text-sm text-slate-500">
                        {{ ctrans("No free times right now. Please check again later.") }}
                    </div>

                    <template v-else>
                        <DatePicker
                            v-model="selectedDate"
                            inline
                            :showOtherMonths="false"
                            :minDate="today"
                            :maxDate="maxDate"
                            :disabledDates="disabledDates"
                            :dt="datePickerDt"
                            :pt="datePickerPt" />

                        <div v-if="timezone" class="mt-6">
                            <div class="text-sm font-bold text-slate-800">{{ ctrans("Time zone") }}</div>
                            <div class="mt-1 flex items-center gap-1.5 text-sm text-slate-700">
                                <FontAwesomeIcon icon="fal fa-globe" fixed-width aria-hidden="true" />
                                {{ timezone }}<template v-if="timezoneNow"> ({{ timezoneNow }})</template>
                            </div>
                        </div>
                    </template>
                </div>

                <div v-if="selectedDate" class="flex flex-col lg:w-[220px] lg:shrink-0 lg:pt-14">
                    <div class="mb-4 shrink-0 text-base text-slate-800">{{ dayHeading(selectedDate) }}</div>
                    <div class="relative min-h-0 flex-1">
                        <div class="flex max-h-96 flex-col gap-2.5 overflow-y-auto pr-1 [scrollbar-width:thin] lg:absolute lg:inset-0 lg:max-h-none">
                            <div v-for="time in timesForDate" :key="time" class="flex shrink-0 gap-2">
                                <button
                                    type="button"
                                    class="h-[52px] flex-1 rounded border text-base font-bold transition-all"
                                    :class="time === selectedTime
                                        ? 'border-slate-600 bg-slate-600 text-white'
                                        : 'border-[color-mix(in_srgb,var(--theme-color-0)_50%,white)] text-[var(--theme-color-0)] hover:border-2 hover:border-[var(--theme-color-0)]'"
                                    :aria-pressed="time === selectedTime"
                                    @click="selectedTime = time">
                                    {{ time }}
                                </button>
                                <button
                                    v-if="time === selectedTime" type="button"
                                    class="h-[52px] flex-1 rounded bg-[var(--theme-color-0)] text-base font-bold text-[var(--theme-color-1)] transition hover:opacity-90"
                                    @click="step = 'details'">
                                    {{ ctrans("Next") }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-if="errors.time" class="mt-3 text-sm text-red-600">{{ errors.time }}</p>
                </div>
            </section>

            <form v-else class="flex-1 space-y-4 px-6 py-7" @submit.prevent="submit">
                <h3 class="mb-2 text-xl font-bold text-slate-800">{{ ctrans("Enter Details") }}</h3>

                <div hidden aria-hidden="true">
                    <label>
                        {{ ctrans("Leave this field empty") }}
                        <input v-model="form.website_url" type="text" name="appointment_hp" tabindex="-1" autocomplete="off" />
                    </label>
                </div>

                <div class="text-sm">
                    <label for="appointment-name" class="mb-1.5 block font-bold text-slate-800">{{ ctrans("Name") }} *</label>
                    <InputText id="appointment-name" v-model="form.contact_name" required autocomplete="name" :pt="fieldPt" :invalid="!!errors.contact_name" />
                    <span v-if="errors.contact_name" class="mt-1 block text-red-600">{{ errors.contact_name }}</span>
                </div>
                <div class="text-sm">
                    <label for="appointment-email" class="mb-1.5 block font-bold text-slate-800">{{ ctrans("Email") }} *</label>
                    <InputText id="appointment-email" v-model="form.email" type="email" required autocomplete="email" :pt="fieldPt" :invalid="!!errors.email" />
                    <span v-if="errors.email" class="mt-1 block text-red-600">{{ errors.email }}</span>
                </div>
                <div class="text-sm">
                    <label for="appointment-phone" class="mb-1.5 block font-bold text-slate-800">
                        <template v-if="selectedType.meeting_mode === 'video_call'">
                            <FontAwesomeIcon :icon="faWhatsapp" class="text-green-600" fixed-width aria-hidden="true" /> {{ ctrans("WhatsApp number") }} *
                        </template>
                        <template v-else>{{ ctrans("Phone") }}</template>
                    </label>
                    <InputText
                        id="appointment-phone"
                        v-model="form.phone"
                        type="tel"
                        autocomplete="tel"
                        :required="selectedType.meeting_mode === 'video_call'"
                        :placeholder="selectedType.meeting_mode === 'video_call' ? '+44 7700 900123' : ''"
                        :pt="fieldPt"
                        :invalid="!!errors.phone" />
                    <span v-if="selectedType.meeting_mode === 'video_call' && !errors.phone" class="mt-1 block text-slate-500">
                        {{ ctrans("We call you on WhatsApp. Include your country code.") }}
                    </span>
                    <span v-if="errors.phone" class="mt-1 block text-red-600">{{ errors.phone }}</span>
                </div>
                <div v-if="selectedType.meeting_mode === 'store_visit'" class="text-sm">
                    <label for="appointment-visitors" class="mb-1.5 block font-bold text-slate-800">{{ ctrans("People coming") }}</label>
                    <InputNumber v-model="form.number_visitors" inputId="appointment-visitors" :min="1" :max="20" :pt="{ pcInputText: { root: { class: 'w-24' } } }" />
                </div>
                <div class="text-sm">
                    <label for="appointment-notes" class="mb-1.5 block font-bold text-slate-800">{{ ctrans("Please share anything that will help prepare for our meeting.") }}</label>
                    <Textarea id="appointment-notes" v-model="form.notes" rows="3" autoResize class="w-full" />
                </div>
                <div v-if="showMarketingOptIn" class="flex items-start gap-2 text-sm">
                    <Checkbox v-model="form.marketing_opt_in" binary inputId="appointment-marketing-opt-in" />
                    <label for="appointment-marketing-opt-in">{{ ctrans("Send me news and offers by email") }}</label>
                </div>

                <p v-if="errors.general" class="text-sm text-red-600">{{ errors.general }}</p>

                <button
                    type="submit"
                    :disabled="isSubmitting"
                    class="inline-flex items-center justify-center gap-2 rounded-full bg-[var(--theme-color-0)] px-6 py-3 text-sm font-bold text-[var(--theme-color-1)] transition hover:opacity-90 disabled:opacity-60">
                    <LoadingIcon v-if="isSubmitting" />
                    {{ ctrans("Request appointment") }}
                </button>
            </form>
        </div>
    </div>
</template>
