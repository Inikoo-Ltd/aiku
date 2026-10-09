<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { computed, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import Select from "primevue/select"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faCalendarEdit, faCalendarCheck } from "@fal"

library.add(faCheck, faTimes, faCalendarEdit, faCalendarCheck)

export type AppointmentForActions = {
    id: number
    contact_name: string
    date_label: string
    time: string
    can_accept: boolean
    can_decline: boolean
    can_reschedule: boolean
}

const props = defineProps<{
    appointment: AppointmentForActions
    compact?: boolean
}>()

type Options = { staff: { id: number, name: string }[], free_slots: Record<string, string[]>, user_id: number | null }

const options = ref<Options | null>(null)
const isLoadingOptions = ref(false)
const optionsError = ref("")

const loadOptions = async () => {
    if (options.value) {
        return
    }
    isLoadingOptions.value = true
    optionsError.value = ""
    try {
        const { data } = await axios.get(route("grp.json.appointment.action_options", { appointment: props.appointment.id }))
        options.value = data
    } catch {
        optionsError.value = ctrans("Could not load the options. Please try again.")
    }
    isLoadingOptions.value = false
}

const isSending = ref<"" | "accept" | "decline" | "reschedule">("")
const errors = ref<Record<string, string>>({})

const send = (action: "accept" | "decline" | "reschedule", data: Record<string, unknown>, onSuccess: () => void) => {
    isSending.value = action
    errors.value = {}
    router.patch(route(`grp.models.appointment.${action}`, { appointment: props.appointment.id }), data, {
        preserveScroll: true,
        onSuccess: () => {
            options.value = null
            onSuccess()
        },
        onError: (serverErrors) => (errors.value = serverErrors as Record<string, string>),
        onFinish: () => (isSending.value = ""),
    })
}

const isAcceptOpen = ref(false)
const acceptUserId = ref<number | null>(null)

const openAccept = async () => {
    errors.value = {}
    isAcceptOpen.value = true
    await loadOptions()
    const staff = options.value?.staff ?? []
    acceptUserId.value = staff.some(person => person.id === options.value?.user_id) ? options.value!.user_id : staff.length === 1 ? staff[0].id : null
}

const accept = () => send("accept", { user_id: acceptUserId.value }, () => (isAcceptOpen.value = false))

const isDeclineOpen = ref(false)
const declineReason = ref("")

const openDecline = () => {
    errors.value = {}
    declineReason.value = ""
    isDeclineOpen.value = true
}

const decline = () => send("decline", { reason: declineReason.value }, () => (isDeclineOpen.value = false))

const isRescheduleOpen = ref(false)
const rescheduleDate = ref<Date | null>(null)
const rescheduleTime = ref<string | null>(null)

const toIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}

const freeSlots = computed(() => options.value?.free_slots ?? {})
const freeDates = computed(() => Object.keys(freeSlots.value).sort())
const today = new Date()
today.setHours(0, 0, 0, 0)
const lastFreeDate = computed(() => (freeDates.value.length ? fromIsoDate(freeDates.value[freeDates.value.length - 1]) : today))
const disabledDates = computed(() => {
    const free = new Set(freeDates.value)
    const disabled: Date[] = []
    for (const day = new Date(today); day <= lastFreeDate.value; day.setDate(day.getDate() + 1)) {
        if (!free.has(toIsoDate(day))) {
            disabled.push(new Date(day))
        }
    }
    return disabled
})
const timesForDate = computed(() => (rescheduleDate.value ? freeSlots.value[toIsoDate(rescheduleDate.value)] ?? [] : []))

watch(rescheduleDate, () => (rescheduleTime.value = null))

const openReschedule = async () => {
    errors.value = {}
    rescheduleDate.value = null
    rescheduleTime.value = null
    isRescheduleOpen.value = true
    await loadOptions()
}

const reschedule = () => {
    if (!rescheduleDate.value || !rescheduleTime.value) {
        return
    }
    send("reschedule", { date: toIsoDate(rescheduleDate.value), time: rescheduleTime.value }, () => (isRescheduleOpen.value = false))
}

const datePickerDt = {
    date: {
        selectedBackground: "var(--app-accent)",
        selectedColor: "var(--app-accent-text)",
        hoverBackground: "var(--app-accent-soft)",
        hoverColor: "var(--app-accent-strong)",
        focusRing: { color: "var(--app-accent)" },
    },
}
const datePickerPt = {
    root: { class: "w-full" },
    panel: { class: "!block w-full !overflow-visible !border-0 !p-0" },
    dayView: { class: "!w-full" },
}
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"
</script>

<template>
    <div class="flex flex-wrap items-center gap-2" :class="compact ? 'justify-end' : ''">
        <Button v-if="appointment.can_accept" type="positive" icon="fal fa-check" :size="compact ? 'xs' : undefined" :label="ctrans('Accept')" @click="openAccept" />
        <Button v-if="appointment.can_reschedule" type="secondary" icon="fal fa-calendar-edit" :size="compact ? 'xs' : undefined" :label="ctrans('Reschedule')" @click="openReschedule" />
        <Button v-if="appointment.can_decline" type="negative" icon="fal fa-times" :size="compact ? 'xs' : undefined" :label="ctrans('Decline')" @click="openDecline" />

        <Dialog v-model:visible="isAcceptOpen" modal :header="ctrans('Accept appointment')" :style="{ width: '28rem' }" :breakpoints="{ '575px': '95vw' }">
            <p class="mb-4 text-sm text-gray-600">
                {{ ctrans(":name, :date at :time. Who arranges this appointment?", { name: appointment.contact_name, date: appointment.date_label, time: appointment.time }) }}
            </p>
            <div v-if="isLoadingOptions" class="flex justify-center py-4 text-gray-400"><LoadingIcon /></div>
            <p v-else-if="optionsError" class="text-sm text-red-600">{{ optionsError }}</p>
            <p v-else-if="!options?.staff.length" class="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                {{ ctrans("Nobody arranges this type of appointment yet. Add staff under Appointments › Staff first.") }}
            </p>
            <template v-else>
                <label for="accept-staff" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Staff") }}</label>
                <Select
                    v-model="acceptUserId"
                    inputId="accept-staff"
                    :options="options.staff"
                    optionLabel="name"
                    optionValue="id"
                    :placeholder="ctrans('Choose a person')"
                    filter
                    class="w-full"
                    :class="fieldFocusClass" />
            </template>
            <p v-if="errors.user_id || errors.state" class="mt-2 text-sm text-red-600">{{ errors.user_id || errors.state }}</p>
            <template #footer>
                <Button type="tertiary" :label="ctrans('Back')" @click="isAcceptOpen = false" />
                <Button type="positive" icon="fal fa-check" :label="ctrans('Accept')" :disabled="!acceptUserId" :loading="isSending === 'accept'" @click="accept" />
            </template>
        </Dialog>

        <Dialog v-model:visible="isDeclineOpen" modal :header="ctrans('Decline appointment')" :style="{ width: '28rem' }" :breakpoints="{ '575px': '95vw' }">
            <p class="mb-3 text-sm text-gray-600">
                {{ ctrans(":name, :date at :time. The time becomes free again for other visitors.", { name: appointment.contact_name, date: appointment.date_label, time: appointment.time }) }}
            </p>
            <label :for="`decline-reason-${appointment.id}`" class="mb-1 block text-sm font-medium text-gray-700">{{ ctrans("Reason") }}</label>
            <Textarea :id="`decline-reason-${appointment.id}`" v-model="declineReason" rows="3" autoResize class="w-full" />
            <p v-if="errors.reason || errors.state" class="mt-1 text-sm text-red-600">{{ errors.reason || errors.state }}</p>
            <template #footer>
                <Button type="tertiary" :label="ctrans('Back')" @click="isDeclineOpen = false" />
                <Button type="negative" icon="fal fa-times" :label="ctrans('Decline')" :loading="isSending === 'decline'" @click="decline" />
            </template>
        </Dialog>

        <Dialog v-model:visible="isRescheduleOpen" modal :header="ctrans('Reschedule appointment')" :style="{ width: '44rem' }" :breakpoints="{ '768px': '95vw' }">
            <p class="mb-4 text-sm text-gray-600">
                {{ ctrans("Now :date, :time. Pick a free time; the appointment is accepted at the new time.", { date: appointment.date_label, time: appointment.time }) }}
            </p>
            <div v-if="isLoadingOptions" class="flex justify-center py-6 text-gray-400"><LoadingIcon /></div>
            <p v-else-if="optionsError" class="text-sm text-red-600">{{ optionsError }}</p>
            <div v-else-if="!freeDates.length" class="py-6 text-center text-sm text-gray-500">
                {{ ctrans("There are no free times in the booking window of this appointment type.") }}
            </div>
            <div v-else class="flex flex-col gap-5 md:flex-row">
                <div class="md:w-80">
                    <DatePicker v-model="rescheduleDate" inline :minDate="today" :maxDate="lastFreeDate" :disabledDates="disabledDates" :dt="datePickerDt" :pt="datePickerPt" />
                </div>
                <div class="flex-1">
                    <div v-if="!rescheduleDate" class="text-sm text-gray-500">{{ ctrans("Choose a date first.") }}</div>
                    <div v-else class="grid grid-cols-3 gap-2">
                        <button
                            v-for="time in timesForDate" :key="time" type="button"
                            class="rounded-md border px-2 py-2 text-sm font-medium transition"
                            :class="time === rescheduleTime
                                ? 'border-[--app-accent] bg-[--app-accent] text-[--app-accent-text]'
                                : 'border-gray-300 text-gray-700 hover:border-[--app-accent] hover:text-[--app-accent]'"
                            :aria-pressed="time === rescheduleTime"
                            @click="rescheduleTime = time">
                            {{ time }}
                        </button>
                    </div>
                    <p v-if="errors.time || errors.date || errors.state" class="mt-2 text-sm text-red-600">{{ errors.time || errors.date || errors.state }}</p>
                </div>
            </div>
            <template #footer>
                <Button type="tertiary" :label="ctrans('Back')" @click="isRescheduleOpen = false" />
                <Button type="primary" icon="fal fa-calendar-check" :label="ctrans('Move appointment')" :disabled="!rescheduleDate || !rescheduleTime" :loading="isSending === 'reschedule'" @click="reschedule" />
            </template>
        </Dialog>
    </div>
</template>
