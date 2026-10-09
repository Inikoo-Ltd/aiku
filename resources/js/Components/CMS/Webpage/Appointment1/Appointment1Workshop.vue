<script setup lang="ts">
import { set } from "lodash-es"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"
import AppointmentBooking from "@/Components/CMS/Webpage/Appointment/AppointmentBooking.vue"
import type { AppointmentBookingPayload } from "@/Components/CMS/Webpage/Appointment/AppointmentBooking.vue"
import { demoAppointmentTypes, demoBook } from "@/Components/CMS/Webpage/Appointment/demoAppointmentTypes"

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: object
    screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
    (e: "update:modelValue", value: any): void
    (e: "autoSave"): void
}>()

const appointmentTypes = demoAppointmentTypes()

const book = (payload: AppointmentBookingPayload) =>
    demoBook(appointmentTypes.find(type => type.id === payload.appointment_type_id), payload.date, payload.time)

const hasText = (html?: string | null) => !!html?.replace(/<[^>]*>/g, "").trim()

const editorToggle = [
    "heading1", "heading2", "heading3", "fontSize", "bold", "italic", "underline", "fontFamily",
    "alignLeft", "alignRight", "alignCenter", "customLink", "undo", "redo", "color", "clear",
]

const updateText = (key: "headline" | "description", value: string) => {
    set(props.modelValue, ["value", key], value)
    emits("autoSave")
}
</script>

<template>
    <div :style="getStyles(modelValue?.container?.properties, screenType)">
        <div class="mx-auto max-w-5xl px-4 py-10">
            <div class="mb-2 text-center text-2xl font-semibold">
                <Editor :modelValue="modelValue?.value?.headline" :toggle="editorToggle" class="hover-text-input"
                    @update:modelValue="(value: string) => updateText('headline', value)" />
            </div>
            <div class="mb-8 text-center">
                <Editor :modelValue="modelValue?.value?.description" :toggle="editorToggle" class="hover-text-input"
                    @update:modelValue="(value: string) => updateText('description', value)" />
            </div>
            <AppointmentBooking
                :appointmentTypes="appointmentTypes"
                :timezone="Intl.DateTimeFormat().resolvedOptions().timeZone"
                :organizerName="webpageData?.website?.name ?? webpageData?.shop?.name ?? ''"
                :showPickerHeading="!hasText(modelValue?.value?.headline)"
                :showMarketingOptIn="modelValue?.settings?.show_marketing_opt_in ?? true"
                :book="book" />
        </div>
    </div>
</template>
