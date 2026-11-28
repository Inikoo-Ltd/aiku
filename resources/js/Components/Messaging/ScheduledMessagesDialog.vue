<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import { Link } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faClock, faExclamationTriangle, faPaperPlane, faPencil, faTimes, faTrashAlt } from "@fal"
import ScheduleTimeDialog from "@/Components/Messaging/ScheduleTimeDialog.vue"
import { errorOf, useScheduledMessages, wallClockLabel, type StaffMessageSchedule } from "@/Components/Messaging/scheduledMessages"

library.add(faCheck, faClock, faExclamationTriangle, faPaperPlane, faPencil, faTimes, faTrashAlt)

const visible = defineModel<boolean>("visible", { default: false })
const { schedules, load } = useScheduledMessages()

const loaded = ref(false)
const busyId = ref<number | null>(null)
const errors = ref<Record<number, string>>({})
const editing = ref<{ id: number; text: string; sendAt: string } | null>(null)
const pickingTime = ref(false)

watch(visible, async (open) => {
    if (!open) return
    editing.value = null
    errors.value = {}
    await load()
    loaded.value = true
})

const act = async (schedule: StaffMessageSchedule, request: () => Promise<unknown>) => {
    busyId.value = schedule.id
    delete errors.value[schedule.id]
    try {
        await request()
        editing.value = null
    } catch (exception) {
        errors.value[schedule.id] = errorOf(exception, ctrans("That did not work. Try again."))
    } finally {
        busyId.value = null
        await load()
    }
}

const sendNow = (schedule: StaffMessageSchedule) => act(schedule, () => axios.post(route("grp.chat.staff.scheduled.send", { staffMessageSchedule: schedule.id })))
const cancel = (schedule: StaffMessageSchedule) => act(schedule, () => axios.delete(route("grp.chat.staff.scheduled.cancel", { staffMessageSchedule: schedule.id })))
const save = (schedule: StaffMessageSchedule) => editing.value && act(schedule, () => axios.patch(route("grp.chat.staff.scheduled.update", { staffMessageSchedule: schedule.id }), { text: schedule.has_mentions ? null : editing.value!.text, send_at: editing.value!.sendAt }))
const startEdit = (schedule: StaffMessageSchedule) => (editing.value = { id: schedule.id, text: schedule.text, sendAt: schedule.send_at_local })
const setTime = (local: string) => {
    if (editing.value) editing.value.sendAt = local
    pickingTime.value = false
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('Scheduled messages')" :style="{ width: '34rem' }" :breakpoints="{ '640px': '95vw' }">
        <div v-if="!loaded" class="flex justify-center py-6"><LoadingIcon /></div>
        <p v-else-if="!schedules.length" class="py-4 text-sm text-gray-500">{{ ctrans("Nothing is waiting to be sent. Use Send later beside the message box.") }}</p>
        <ul v-else class="divide-y divide-gray-100">
            <li v-for="schedule in schedules" :key="schedule.id" class="py-3">
                <div class="flex items-baseline gap-x-2 text-xs">
                    <Link :href="schedule.target.url" class="truncate font-semibold text-gray-800 hover:underline" @click="visible = false">{{ schedule.target.name }}</Link>
                    <span v-if="schedule.is_reply" class="shrink-0 text-gray-400">{{ ctrans("reply") }}</span>
                    <span class="ml-auto shrink-0 whitespace-nowrap" :class="schedule.state === 'failed' ? 'text-red-600' : 'text-gray-500'">
                        <FontAwesomeIcon :icon="schedule.state === 'failed' ? 'fal fa-exclamation-triangle' : 'fal fa-clock'" fixed-width aria-hidden="true" />
                        {{ schedule.state === "failed" ? ctrans("Not sent") : schedule.state === "sending" ? ctrans("Sending…") : wallClockLabel(schedule.send_at_local) }}
                    </span>
                </div>

                <template v-if="editing?.id === schedule.id">
                    <p v-if="schedule.has_mentions" class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-700">{{ schedule.text }}</p>
                    <p v-if="schedule.has_mentions" class="mt-0.5 text-xs text-gray-500">{{ ctrans("This message mentions people, so only its time can change here.") }}</p>
                    <Textarea v-else v-model="editing.text" auto-resize rows="2" class="mt-1.5 w-full text-sm" :aria-label="ctrans('Message')" />
                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        <Button type="tertiary" size="xs" :label="wallClockLabel(editing.sendAt)" @click="pickingTime = true">
                            <template #icon><FontAwesomeIcon icon="fal fa-clock" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <span class="ml-auto" />
                        <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="editing = null">
                            <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <Button type="tertiary" size="xs" :label="ctrans('Save')" :disabled="busyId === schedule.id || !editing.text.trim()" @click="save(schedule)">
                            <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                </template>
                <template v-else>
                    <p class="mt-1 line-clamp-3 whitespace-pre-wrap break-words text-sm text-gray-700">{{ schedule.text }}</p>
                    <p v-if="schedule.failure_reason" class="mt-1 text-xs text-red-600">{{ schedule.failure_reason }}</p>
                    <div v-if="schedule.state === 'failed'" class="mt-1.5 flex flex-wrap gap-2">
                        <Button type="tertiary" size="xs" :label="ctrans('Dismiss')" :disabled="busyId === schedule.id" @click="cancel(schedule)">
                            <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                    <div v-if="schedule.state === 'pending'" class="mt-1.5 flex flex-wrap gap-2">
                        <Button type="tertiary" size="xs" :label="ctrans('Edit')" :disabled="busyId === schedule.id" @click="startEdit(schedule)">
                            <template #icon><FontAwesomeIcon icon="fal fa-pencil" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <Button type="tertiary" size="xs" :label="ctrans('Send now')" :disabled="busyId === schedule.id" @click="sendNow(schedule)">
                            <template #icon><FontAwesomeIcon icon="fal fa-paper-plane" class="text-green-600" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <Button type="tertiary" size="xs" :label="ctrans('Don\'t send')" :disabled="busyId === schedule.id" @click="cancel(schedule)">
                            <template #icon><FontAwesomeIcon icon="fal fa-trash-alt" class="text-amber-500" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                </template>
                <p v-if="errors[schedule.id]" class="mt-1 text-xs text-red-600" role="alert">{{ errors[schedule.id] }}</p>
            </li>
        </ul>
        <p class="mt-3 text-xs text-gray-400">{{ ctrans("Files and pictures cannot be scheduled yet, only text.") }}</p>
        <ScheduleTimeDialog v-model:visible="pickingTime" :header="ctrans('Change the time')" :confirm-label="ctrans('Use this time')" :initial="editing?.sendAt" @pick="setTime" />
    </Dialog>
</template>
