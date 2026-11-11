<!--
  - Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
  - Created: Wed, 11 Nov 2026
  - Copyright (c) 2026
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClock, faCheck, faTimes, faExclamationTriangle } from "@fal"
import { routeType } from "@/types/route"

interface PublishScheduleState {
    scheduled_at: string | null
    scheduled_by: string | null
}

const props = defineProps<{
    publishSchedule: PublishScheduleState & {
        store_route: routeType
        delete_route: routeType
    }
    saveDraft: () => Promise<boolean>
}>()

const scheduledAt = ref<string | null>(props.publishSchedule.scheduled_at)
const scheduledBy = ref<string | null>(props.publishSchedule.scheduled_by)

const isDialogVisible = ref(false)
const publishAt = ref<Date | null>(null)
const minimumPublishAt = ref(new Date())
const isSaving = ref(false)
const isRemoving = ref(false)
const errorMessage = ref<string | null>(null)

const browserTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const scheduledAtLabel = computed(() => scheduledAt.value ? useFormatTime(scheduledAt.value, { formatTime: "short-datetime" }) : null)

const openDialog = () => {
    publishAt.value = scheduledAt.value ? new Date(scheduledAt.value) : null
    minimumPublishAt.value = new Date()
    errorMessage.value = null
    isDialogVisible.value = true
}

const applySchedule = (schedule: PublishScheduleState) => {
    scheduledAt.value = schedule.scheduled_at
    scheduledBy.value = schedule.scheduled_by
}

const messageFromError = (error: any): string =>
    error?.response?.data?.errors?.publish_scheduled_at?.[0]
    ?? error?.response?.data?.message
    ?? error?.message
    ?? ctrans("Something went wrong")

const saveSchedule = async () => {
    if (!publishAt.value) {
        return
    }

    isSaving.value = true
    errorMessage.value = null

    try {
        const isDraftSaved = await props.saveDraft()
        if (!isDraftSaved) {
            errorMessage.value = ctrans("The draft could not be saved, so the blog was not scheduled")
            return
        }

        const storeRoute = props.publishSchedule.store_route
        const response = await axios.post(route(storeRoute.name, storeRoute.parameters), {
            publish_scheduled_at: publishAt.value.toISOString(),
        })

        applySchedule(response.data)
        isDialogVisible.value = false
    } catch (error) {
        errorMessage.value = messageFromError(error)
    } finally {
        isSaving.value = false
    }
}

watch(() => props.publishSchedule, applySchedule)

const clearSchedule = () => {
    applySchedule({ scheduled_at: null, scheduled_by: null })
}

defineExpose({ clearSchedule })

const removeSchedule = async () => {
    isRemoving.value = true
    errorMessage.value = null

    try {
        const deleteRoute = props.publishSchedule.delete_route
        const response = await axios.delete(route(deleteRoute.name, deleteRoute.parameters))

        applySchedule(response.data)
        isDialogVisible.value = false
    } catch (error) {
        errorMessage.value = messageFromError(error)
    } finally {
        isRemoving.value = false
    }
}
</script>

<template>
    <div>
        <Button
            type="tertiary"
            :label="scheduledAtLabel ? ctrans('Scheduled :date', { date: scheduledAtLabel }) : ctrans('Schedule')"
            :tooltip="ctrans('Publish automatically at a set date and time')"
            @click="openDialog"
        >
            <template #icon>
                <FontAwesomeIcon :icon="faClock" :class="scheduledAt ? 'text-amber-500' : ''" fixed-width aria-hidden="true" />
            </template>
        </Button>

        <Dialog
            v-model:visible="isDialogVisible"
            modal
            :header="ctrans('Schedule publishing')"
            :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }"
            :draggable="false"
        >
            <div class="space-y-4 text-sm">
                <p class="text-gray-600">
                    {{ ctrans("The blog is published automatically at this time, with the draft as it is then. Publishing it by hand before then removes the schedule.") }}
                </p>

                <div>
                    <label for="publish-schedule-at" class="mb-1 block font-medium text-gray-700">
                        <span>{{ ctrans("Publish at") }} <span class="text-red-500">*</span></span>
                    </label>
                    <DatePicker
                        v-model="publishAt"
                        inputId="publish-schedule-at"
                        showTime
                        hourFormat="24"
                        dateFormat="d M yy"
                        :minDate="minimumPublishAt"
                        showIcon
                        iconDisplay="input"
                        fluid
                        :class="fieldFocusClass"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ ctrans("Time in your time zone (:timeZone)", { timeZone: browserTimeZone }) }}</p>
                </div>

                <div v-if="scheduledAt" class="flex items-center justify-between gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2">
                    <span class="text-amber-800">
                        {{ ctrans("Scheduled for :date by :user", { date: scheduledAtLabel, user: scheduledBy ?? "-" }) }}
                    </span>
                    <Button type="tertiary" size="xs" class="shrink-0" :label="ctrans('Remove')" :loading="isRemoving" :disabled="isSaving" @click="removeSchedule">
                        <template #icon>
                            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>

                <p v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>
            </div>

            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isDialogVisible = false">
                        <template #icon>
                            <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="primary" full :label="ctrans('Save schedule')" :loading="isSaving" :disabled="!publishAt || isRemoving" @click="saveSchedule">
                        <template #icon>
                            <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
