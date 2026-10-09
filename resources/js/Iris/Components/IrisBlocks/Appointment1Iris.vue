<script setup lang="ts">
import { inject, onMounted, ref } from "vue"
import axios from "axios"
import { getStyles } from "@/Composables/styles"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import AppointmentBooking from "@/Components/CMS/Webpage/Appointment/AppointmentBooking.vue"
import type { AppointmentBookingPayload, AppointmentTypeOption } from "@/Components/CMS/Webpage/Appointment/AppointmentBooking.vue"
import { demoAppointmentTypes, demoBook } from "@/Components/CMS/Webpage/Appointment/demoAppointmentTypes"

const props = defineProps<{
    fieldValue: any
    theme?: any
    screenType: "mobile" | "tablet" | "desktop"
}>()

const layout: any = inject("layout", retinaLayoutStructure)
const isLive = !!layout?.iris?.website?.id

const appointmentTypes = ref<AppointmentTypeOption[]>([])
const timezone = ref(Intl.DateTimeFormat().resolvedOptions().timeZone)
const organizerName = ref<string>(layout?.iris?.website?.name ?? "")
const isLoading = ref(true)

onMounted(async () => {
    if (!isLive) {
        appointmentTypes.value = demoAppointmentTypes()
        isLoading.value = false
        return
    }
    try {
        const { data } = await axios.get(route("iris.json.appointments.index"))
        appointmentTypes.value = data.appointment_types
        timezone.value = data.timezone
        organizerName.value = data.shop_name
    } catch {
        appointmentTypes.value = []
    }
    isLoading.value = false
})

const hasText = (html?: string | null) => !!html?.replace(/<[^>]*>/g, "").trim()

const book = async (payload: AppointmentBookingPayload) => {
    if (!isLive) {
        return demoBook(appointmentTypes.value.find(type => type.id === payload.appointment_type_id), payload.date, payload.time)
    }
    const { data } = await axios.post(route("iris.models.appointment.store"), payload)
    return data
}
</script>

<template>
    <div
        :id="fieldValue?.id ? fieldValue.id : 'appointment-1'"
        component="appointment-1"
        :style="getStyles(fieldValue?.container?.properties, screenType)">
        <div class="mx-auto max-w-5xl px-4 py-10">
            <div v-if="hasText(fieldValue?.value?.headline) || hasText(fieldValue?.value?.description)" class="mb-8 space-y-2 text-center">
                <div v-if="hasText(fieldValue?.value?.headline)" class="text-2xl font-semibold" v-html="fieldValue.value.headline" />
                <div v-if="hasText(fieldValue?.value?.description)" v-html="fieldValue.value.description" />
            </div>
            <AppointmentBooking
                :appointmentTypes="appointmentTypes"
                :timezone="timezone"
                :organizerName="organizerName"
                :showPickerHeading="!hasText(fieldValue?.value?.headline)"
                :isLoading="isLoading"
                :showMarketingOptIn="fieldValue?.settings?.show_marketing_opt_in ?? true"
                :prefill="{ contact_name: layout?.iris_variables?.name, email: layout?.iris_variables?.email }"
                :book="book" />
        </div>
    </div>
</template>
