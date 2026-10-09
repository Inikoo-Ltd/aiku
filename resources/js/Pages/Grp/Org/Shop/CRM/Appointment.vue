<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AppointmentActions from "@/Components/CRM/AppointmentActions.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faCalendar, faCalendarCheck, faClock, faMapMarkerAlt, faStoreAlt, faVideo, faUser, faEnvelope, faPhone,
    faUsers, faStickyNote, faHourglassHalf, faBan, faCheckCircle, faUserSlash, faTimesCircle, faCheck, faTimes, faCalendarEdit, faGlobe,
} from "@fal"

library.add(faCalendar, faCalendarCheck, faClock, faMapMarkerAlt, faStoreAlt, faVideo, faUser, faEnvelope, faPhone, faUsers, faStickyNote, faHourglassHalf, faBan, faCheckCircle, faUserSlash, faTimesCircle, faCheck, faTimes, faCalendarEdit, faGlobe)

type RouteDefinition = { name: string, parameters: Record<string, string | number> }

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    canEdit: boolean
    appointment: {
        id: number
        state: string
        state_label: string
        state_icon: { icon: string, class: string, tooltip: string }
        state_reason: string | null
        date: string
        time: string
        ends_time: string
        date_label: string
        is_past: boolean
        timezone: string
        appointment_type: { name: string, meeting_mode: string, meeting_mode_label: string, location: string | null, duration_minutes: number }
        staff_name: string | null
        contact_name: string
        email: string | null
        phone: string | null
        whatsapp_url: string | null
        number_visitors: number
        notes: string | null
        visitor: { type: string, label: string, name: string, route: RouteDefinition } | null
        source_label: string
        created_by: string | null
        created_at: string
        accepted_at: string | null
        declined_at: string | null
        cancelled_at: string | null
        can_accept: boolean
        can_decline: boolean
        can_reschedule: boolean
    }
}>()

const statusTone = computed(() => ({
    requested: "border-amber-200 bg-amber-50 text-amber-800",
    accepted: "border-green-200 bg-green-50 text-green-800",
    completed: "border-green-200 bg-green-50 text-green-800",
    declined: "border-red-200 bg-red-50 text-red-800",
    cancelled: "border-red-200 bg-red-50 text-red-800",
    no_show: "border-gray-200 bg-gray-50 text-gray-700",
}[props.appointment.state] ?? "border-gray-200 bg-gray-50 text-gray-700"))

const statusMessage = computed(() => ({
    requested: ctrans("Booked on the website and waiting for your answer. Accept it, decline it or offer another time."),
    accepted: props.appointment.is_past ? ctrans("This visit has passed. Set it to completed or no show from Edit.") : ctrans("Confirmed. The visitor is expected at this time."),
    declined: ctrans("Declined. The time is free again for other visitors."),
    cancelled: ctrans("Cancelled. The time is free again for other visitors."),
    completed: ctrans("The visit took place."),
    no_show: ctrans("The visitor did not come."),
}[props.appointment.state] ?? ""))

</script>

<template>
    <div>
        <Head :title="capitalize(title)" />
        <PageHeading :data="pageHead" />

        <div class="space-y-6 p-4 md:p-6">
            <div class="flex flex-col gap-4 rounded-lg border px-5 py-4 lg:flex-row lg:items-center" :class="statusTone">
                <div class="flex flex-1 items-start gap-3">
                    <FontAwesomeIcon :icon="appointment.state_icon.icon" :class="appointment.state_icon.class" class="mt-0.5 text-2xl" fixed-width aria-hidden="true" />
                    <div>
                        <div class="text-lg font-semibold">{{ appointment.state_label }}</div>
                        <div class="text-sm">{{ statusMessage }}</div>
                        <div v-if="appointment.state === 'declined' && appointment.state_reason" class="mt-1 text-sm">
                            <span class="font-medium">{{ ctrans("Reason") }}:</span> {{ appointment.state_reason }}
                        </div>
                    </div>
                </div>

                <AppointmentActions v-if="canEdit" :appointment="appointment" />
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="rounded-lg border border-gray-200 bg-white p-5 lg:col-span-2">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Visit") }}</h2>
                    <div class="text-xl font-semibold text-gray-900">{{ appointment.date_label }}</div>
                    <div class="mt-1 text-lg text-gray-700">
                        {{ appointment.time }}–{{ appointment.ends_time }}
                        <span class="ml-1 text-sm text-gray-500"><FontAwesomeIcon icon="fal fa-globe" fixed-width aria-hidden="true" /> {{ appointment.timezone }}</span>
                    </div>

                    <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div class="flex gap-2">
                            <FontAwesomeIcon :icon="appointment.appointment_type.meeting_mode === 'video_call' ? 'fal fa-video' : 'fal fa-store-alt'" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                            <div>
                                <dt class="text-gray-500">{{ ctrans("Type") }}</dt>
                                <dd class="font-medium text-gray-900">{{ appointment.appointment_type.name }} · {{ appointment.appointment_type.meeting_mode_label }}</dd>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <FontAwesomeIcon icon="fal fa-clock" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                            <div>
                                <dt class="text-gray-500">{{ ctrans("Duration") }}</dt>
                                <dd class="font-medium text-gray-900">{{ ctrans(":minutes min", { minutes: appointment.appointment_type.duration_minutes }) }}</dd>
                            </div>
                        </div>
                        <div v-if="appointment.appointment_type.location" class="flex gap-2">
                            <FontAwesomeIcon icon="fal fa-map-marker-alt" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                            <div>
                                <dt class="text-gray-500">{{ ctrans("Location") }}</dt>
                                <dd class="font-medium text-gray-900">{{ appointment.appointment_type.location }}</dd>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <FontAwesomeIcon icon="fal fa-user" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                            <div>
                                <dt class="text-gray-500">{{ ctrans("Staff") }}</dt>
                                <dd class="font-medium text-gray-900">{{ appointment.staff_name ?? ctrans("Anyone who arranges this type") }}</dd>
                            </div>
                        </div>
                    </dl>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-5">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Visitor") }}</h2>
                    <div class="flex items-center gap-2">
                        <span class="text-lg font-semibold text-gray-900">{{ appointment.contact_name }}</span>
                        <span v-if="appointment.visitor" class="rounded border border-[--app-accent-muted] bg-[--app-accent-soft] px-1.5 py-px text-xs text-gray-700">{{ appointment.visitor.label }}</span>
                    </div>
                    <Link v-if="appointment.visitor" :href="route(appointment.visitor.route.name, appointment.visitor.route.parameters)" class="mt-1 inline-block text-sm text-[--app-accent] hover:underline">
                        {{ ctrans("Open :type", { type: appointment.visitor.label.toLowerCase() }) }} →
                    </Link>

                    <ul class="mt-4 space-y-2 text-sm">
                        <li v-if="appointment.email" class="flex gap-2">
                            <FontAwesomeIcon icon="fal fa-envelope" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                            <a :href="`mailto:${appointment.email}`" class="text-[--app-accent] hover:underline">{{ appointment.email }}</a>
                        </li>
                        <li v-if="appointment.phone" class="flex flex-wrap items-center gap-2">
                            <FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" fixed-width aria-hidden="true" />
                            <a :href="`tel:${appointment.phone}`" class="text-[--app-accent] hover:underline">{{ appointment.phone }}</a>
                            <a v-if="appointment.whatsapp_url" :href="appointment.whatsapp_url" target="_blank" rel="noopener"
                                class="inline-flex items-center gap-1 rounded-md border border-green-500 px-2 py-0.5 text-xs font-medium text-green-700 hover:bg-green-50">
                                <FontAwesomeIcon :icon="faWhatsapp" fixed-width aria-hidden="true" /> {{ ctrans("Call on WhatsApp") }}
                            </a>
                        </li>
                        <li v-if="appointment.appointment_type.meeting_mode === 'store_visit'" class="flex gap-2">
                            <FontAwesomeIcon icon="fal fa-users" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                            {{ ctrans(":count people coming", { count: appointment.number_visitors }) }}
                        </li>
                    </ul>

                    <div v-if="appointment.notes" class="mt-4 rounded-md bg-gray-50 p-3 text-sm text-gray-700">
                        <div class="mb-1 flex items-center gap-1 font-medium text-gray-500">
                            <FontAwesomeIcon icon="fal fa-sticky-note" fixed-width aria-hidden="true" /> {{ ctrans("Notes") }}
                        </div>
                        <p class="whitespace-pre-line">{{ appointment.notes }}</p>
                    </div>
                </section>
            </div>

            <section class="rounded-lg border border-gray-200 bg-white p-5 text-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("History") }}</h2>
                <ul class="space-y-1.5 text-gray-700">
                    <li>
                        {{ appointment.source_label }}<template v-if="appointment.created_by"> {{ ctrans("by :name", { name: appointment.created_by }) }}</template>,
                        {{ useFormatTime(appointment.created_at, { formatTime: "hm" }) }}
                    </li>
                    <li v-if="appointment.accepted_at">{{ ctrans("Accepted") }}, {{ useFormatTime(appointment.accepted_at, { formatTime: "hm" }) }}</li>
                    <li v-if="appointment.declined_at">{{ ctrans("Declined") }}, {{ useFormatTime(appointment.declined_at, { formatTime: "hm" }) }}</li>
                    <li v-if="appointment.cancelled_at">{{ ctrans("Cancelled") }}, {{ useFormatTime(appointment.cancelled_at, { formatTime: "hm" }) }}</li>
                </ul>
            </section>
        </div>

    </div>
</template>
