<!--
  - Author: eka yudinata (https://github.com/ekayudinata)
  - Copyright (c) 2026, eka yudinata
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { faUsers } from "@fortawesome/free-solid-svg-icons"
import { Message, Popover } from "primevue"
import VueDatePicker from "@vuepic/vue-datepicker"
import { toZonedTime, formatInTimeZone } from "date-fns-tz"
import WhatsappTemplatePreview from "@/Components/Chat/WhatsappTemplatePreview.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"

const props = defineProps<{
    campaign: {
        name: string
        state: string
        state_label: string
        recipients_count: number
        scheduled_at: string | null
        sent_at: string | null
    }
    status: string
    template: {
        label: string
        language: string
        header: { format?: string; text?: string | null } | null
        body: string | null
        footer: string | null
        buttons: { type?: string; text?: string }[]
    } | null
    workshopRoute: routeType
    sendRoute: routeType
    scheduleRoute: routeType
    cancelScheduleRoute: routeType
    isConfigured: boolean
    timeZoneOptions: { label: string; value: string }[]
    defaultShopTimezone: string
    mergeTags: { value: string }[]
    businessName: string
}>()

const inProgress = ref(false)
const scheduleInProgress = ref(false)

const showSchedulePicker = ref(false)
const schedulePicker = ref()
const scheduleDateTime = ref<string | null>(null)
const minDateTime = ref<string>(new Date().toISOString())
const nowUtc = ref(new Date())
const selectedTimezone = ref(props.defaultShopTimezone || "UTC")

const isReady = computed(() => props.status === "ready")
const isScheduled = computed(() => props.status === "scheduled")
const isDraft = computed(() => props.status === "in_process")

// The same three conditions the server enforces, so the tooltip explains a refusal before it happens
const blockedReason = computed(() => {
    if (!props.template) return trans("Choose a template first")
    if (!props.campaign.recipients_count) return trans("This campaign has no recipients")
    if (!props.isConfigured) return trans("WhatsApp is not configured for this shop")

    return null
})

const scheduledInstant = computed(() => {
    if (!scheduleDateTime.value) return null
    const instant = new Date(scheduleDateTime.value)

    return isNaN(instant.getTime()) ? null : instant
})

const schedulePreview = computed(() => {
    const instant = scheduledInstant.value
    if (!instant) return null
    const tz = selectedTimezone.value

    return {
        inSelectedTimezone: formatInTimeZone(instant, tz, "EEEE d MMMM yyyy',' HH:mm"),
        inUtc: formatInTimeZone(instant, "UTC", "d MMM yyyy',' HH:mm"),
        isInThePast: instant.getTime() <= Date.now(),
    }
})

const canConfirmSchedule = computed(() => !!schedulePreview.value && !schedulePreview.value.isInThePast)

const scheduledAtLabel = computed(() => {
    if (!props.campaign.scheduled_at) return null
    const instant = new Date(props.campaign.scheduled_at)
    if (isNaN(instant.getTime())) return null

    return formatInTimeZone(instant, selectedTimezone.value, "d MMM yyyy',' HH:mm")
})

const sentAtLabel = computed(() => {
    if (!props.campaign.sent_at) return null
    const instant = new Date(props.campaign.sent_at)
    if (isNaN(instant.getTime())) return null

    return formatInTimeZone(instant, selectedTimezone.value, "d MMM yyyy',' HH:mm")
})

const minTime = computed(() => {
    const noRestriction = { hours: 0, minutes: 0, seconds: 0 }
    const instant = scheduledInstant.value
    if (!instant) return noRestriction
    const tz = selectedTimezone.value

    if (formatInTimeZone(instant, tz, "yyyy-MM-dd") !== formatInTimeZone(nowUtc.value, tz, "yyyy-MM-dd")) {
        return noRestriction
    }

    const nowZoned = toZonedTime(nowUtc.value, tz)

    return {
        hours: nowZoned.getHours(),
        minutes: nowZoned.getMinutes(),
        seconds: nowZoned.getSeconds(),
    }
})

const errorFrom = (exception: any, fallback: string) =>
    exception?.response?.data?.errors?.campaign?.[0] ??
    exception?.response?.data?.message ??
    fallback

const handleSendNow = async () => {
    if (inProgress.value) return
    inProgress.value = true

    try {
        await axios.post(route(props.sendRoute.name, props.sendRoute.parameters))
        notify({ type: "success", title: trans("Success"), text: trans("Campaign is being sent") })
    } catch (exception: any) {
        notify({
            type: "error",
            title: trans("Error"),
            text: errorFrom(exception, trans("Failed to send campaign")),
        })
    } finally {
        inProgress.value = false
        router.reload()
    }
}

const openSchedulePicker = (event: Event) => {
    nowUtc.value = new Date()
    minDateTime.value = nowUtc.value.toISOString()
    scheduleDateTime.value = null

    schedulePicker.value?.show(event)
    showSchedulePicker.value = true
}

const closeSchedulePicker = () => {
    showSchedulePicker.value = false
    schedulePicker.value?.hide()
}

const confirmSchedule = async () => {
    const preview = schedulePreview.value
    if (!preview || preview.isInThePast) {
        notify({
            type: "error",
            title: trans("Error"),
            text: trans("Pick a date and time in the future before scheduling"),
        })
        return
    }

    scheduleInProgress.value = true
    const scheduledAt = scheduledInstant.value!.toISOString()
    closeSchedulePicker()

    try {
        await axios.post(route(props.scheduleRoute.name, props.scheduleRoute.parameters), {
            scheduled_at: scheduledAt,
        })
        notify({
            type: "success",
            title: trans("Success"),
            text: `${trans("Campaign scheduled for")} ${preview.inSelectedTimezone} (${selectedTimezone.value})`,
        })
    } catch (exception: any) {
        notify({
            type: "error",
            title: trans("Error"),
            text: errorFrom(exception, trans("Failed to schedule campaign")),
        })
    } finally {
        scheduleInProgress.value = false
        router.reload()
    }
}

const handleCancelSchedule = async () => {
    if (inProgress.value) return
    inProgress.value = true

    try {
        await axios.post(route(props.cancelScheduleRoute.name, props.cancelScheduleRoute.parameters))
        notify({ type: "success", title: trans("Success"), text: trans("Schedule cancelled") })
    } catch (exception: any) {
        notify({
            type: "error",
            title: trans("Error"),
            text: errorFrom(exception, trans("Failed to cancel the schedule")),
        })
    } finally {
        inProgress.value = false
        router.reload()
    }
}
</script>

<template>
    <div class="w-full max-w-5xl mx-auto px-4 sm:px-6 py-8 space-y-6">
        <Message v-if="isScheduled && scheduledAtLabel" severity="info" :closable="false">
            {{ trans("This campaign is scheduled for") }} {{ scheduledAtLabel }} ({{ selectedTimezone }}).
        </Message>

        <div class="flex flex-col lg:flex-row gap-6">
            <section class="flex-1 rounded-xl border border-gray-200 overflow-hidden self-start w-full">
                <header class="bg-gray-50 border-b border-gray-200 px-4 py-3 text-sm font-medium text-gray-700">
                    {{ trans("Summary") }}
                </header>

                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500">{{ trans("Campaign name") }}</dt>
                        <dd class="text-gray-800 text-right">{{ campaign.name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500">{{ trans("Status") }}</dt>
                        <dd class="text-gray-800 text-right">{{ campaign.state_label }}</dd>
                    </div>
                    <div v-if="scheduledAtLabel" class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500">{{ trans("Scheduled for") }}</dt>
                        <dd class="text-gray-800 text-right">
                            {{ scheduledAtLabel }}
                            <span class="text-gray-500">({{ selectedTimezone }})</span>
                        </dd>
                    </div>
                    <div v-if="sentAtLabel" class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500">{{ trans("Sent on") }}</dt>
                        <dd class="text-gray-800 text-right">
                            {{ sentAtLabel }}
                            <span class="text-gray-500">({{ selectedTimezone }})</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500">{{ trans("WhatsApp number") }}</dt>
                        <dd class="text-gray-800 text-right flex items-center gap-2 justify-end">
                            <FontAwesomeIcon :icon="faWhatsapp" class="text-green-500" />
                            {{ businessName }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500">{{ trans("Template") }}</dt>
                        <dd class="text-gray-800 text-right">
                            <template v-if="template">{{ template.label }} ({{ template.language }})</template>
                            <Link v-else :href="route(workshopRoute.name, workshopRoute.parameters)"
                                class="text-indigo-600 hover:text-indigo-700">
                                {{ trans("Choose a template") }}
                            </Link>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 px-4 py-3">
                        <dt class="text-gray-500 flex items-center gap-2">
                            <FontAwesomeIcon :icon="faUsers" class="text-gray-400" />
                            {{ trans("Recipients") }}
                        </dt>
                        <dd class="text-gray-800 text-right">{{ campaign.recipients_count }}</dd>
                    </div>
                </dl>

                <div v-if="isReady || isScheduled || isDraft"
                    class="border-t border-gray-200 px-4 py-3 flex items-center justify-end gap-2">
                    <!-- Still a draft: the same controls are shown disabled so the tooltip can
                         say what is missing, rather than the whole bar vanishing without a word. -->
                    <template v-if="isDraft">
                        <Button
                            :label="trans('Schedule')"
                            style="tertiary"
                            icon="fal fa-clock"
                            disabled
                            :tooltip="blockedReason ?? undefined" />

                        <Button
                            :label="trans('Send now')"
                            style="primary"
                            icon="fal fa-paper-plane"
                            disabled
                            :tooltip="blockedReason ?? undefined" />
                    </template>

                    <template v-else-if="isReady">
                        <Button
                            :label="trans('Schedule')"
                            style="tertiary"
                            icon="fal fa-clock"
                            :disabled="!!blockedReason || scheduleInProgress"
                            :loading="scheduleInProgress"
                            :tooltip="blockedReason ?? undefined"
                            @click="openSchedulePicker" />

                        <ModalConfirmation
                            :title="trans('Are you sure you want to send this campaign now?')"
                            :description="trans('This will send the WhatsApp template to every selected recipient.')"
                            isFullLoading>
                            <template #default="{ changeModel }">
                                <Button
                                    :label="trans('Send now')"
                                    style="primary"
                                    icon="fal fa-paper-plane"
                                    :disabled="!!blockedReason || inProgress"
                                    :tooltip="blockedReason ?? undefined"
                                    @click="changeModel" />
                            </template>
                            <template #btn-yes>
                                <Button
                                    :label="trans('Send now')"
                                    style="primary"
                                    icon="fal fa-paper-plane"
                                    :loading="inProgress"
                                    :disabled="!!blockedReason || inProgress"
                                    @click="handleSendNow" />
                            </template>
                        </ModalConfirmation>
                    </template>

                    <ModalConfirmation
                        v-else
                        @onYes="handleCancelSchedule"
                        :title="trans('Are you sure you want to cancel this schedule?')"
                        :description="trans('The campaign will not be sent automatically.')"
                        isFullLoading>
                        <template #default="{ changeModel }">
                            <Button
                                :label="trans('Cancel schedule')"
                                style="negative"
                                icon="fal fa-clock"
                                :disabled="inProgress"
                                @click="changeModel" />
                        </template>
                        <template #btn-yes>
                            <Button
                                :label="trans('Cancel schedule')"
                                style="negative"
                                icon="fal fa-clock"
                                :loading="inProgress"
                                :disabled="inProgress"
                                @click="handleCancelSchedule" />
                        </template>
                    </ModalConfirmation>
                </div>
            </section>

            <div class="w-full lg:w-[330px] shrink-0">
                <WhatsappTemplatePreview
                    :body="template?.body"
                    :header="template?.header"
                    :footer="template?.footer"
                    :buttons="template?.buttons ?? []"
                    :businessName="businessName"
                    :mergeTags="mergeTags"
                    :placeholder="trans('No template chosen yet…')" />
            </div>
        </div>

        <Popover ref="schedulePicker" :visible="showSchedulePicker" @hide="closeSchedulePicker" appendTo="body">
            <div class="p-2 min-w-80 bg-white flex flex-col items-center">
                <h3 class="text-lg font-semibold mb-4 text-gray-900">
                    {{ trans("Timezone") }}: <span class="text-red-600">{{ selectedTimezone }}</span>
                </h3>

                <div class="min-w-0 w-full mb-3">
                    <PureMultiselect
                        v-model="selectedTimezone"
                        :placeholder="trans('Select timezone...')"
                        :options="timeZoneOptions || []"
                        :searchable="true"
                        :required="true"
                        caret />
                </div>

                <div class="mb-4 flex justify-center z-10">
                    <VueDatePicker v-model="scheduleDateTime" :min-date="minDateTime" :min-time="minTime"
                        :text-input="true" :inline="true" :enable-time-picker="true" :is-24="true" :minutes-increment="1"
                        :seconds-increment="1" model-type="iso" :auto-apply="true" :open-on-focus="true"
                        :time-picker-inline="true" class="w-full" placeholder="" :teleport="true"
                        :timezone="selectedTimezone" />
                </div>

                <div class="w-full mb-4 rounded-md border px-3 py-2 text-sm"
                    :class="schedulePreview ? 'border-gray-300 bg-gray-50' : 'border-dashed border-gray-300'">
                    <template v-if="schedulePreview">
                        <div class="text-gray-500">{{ trans("This campaign will be sent on") }}</div>
                        <div class="text-gray-900">
                            {{ schedulePreview.inSelectedTimezone }}
                            <span class="text-gray-500">({{ selectedTimezone }})</span>
                        </div>
                        <div class="text-gray-500">{{ schedulePreview.inUtc }} {{ trans("UTC") }}</div>
                        <div v-if="schedulePreview.isInThePast" class="text-red-600">
                            {{ trans("That time has already passed, pick a later one") }}
                        </div>
                    </template>
                    <div v-else class="text-gray-500">
                        {{ trans("Pick a date and time above to see when this campaign will be sent") }}
                    </div>
                </div>

                <div class="flex gap-2 justify-end w-full">
                    <Button :label="trans('Cancel')" style="tertiary" @click="closeSchedulePicker" />
                    <Button :label="trans('Confirm schedule')" style="primary" @click="confirmSchedule"
                        :disabled="!canConfirmSchedule || scheduleInProgress" :loading="scheduleInProgress" />
                </div>
            </div>
        </Popover>
    </div>
</template>
