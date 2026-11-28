<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import Menu from "primevue/menu"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faClock } from "@fal"
import ScheduleTimeDialog from "@/Components/Messaging/ScheduleTimeDialog.vue"
import { inAnHour, tomorrowAtNine, useUserTimezone, wallClockLabel } from "@/Components/Messaging/scheduledMessages"

library.add(faClock)

defineProps<{ disabled?: boolean; disabledReason?: string | null; error?: string | null; busy?: boolean }>()
const emit = defineEmits<{ (e: "pick", local: string): void }>()

const timezone = useUserTimezone()
const menu = ref()
const model = ref<{ label: string; command: () => void }[]>([])
const choosing = ref(false)

const items = () => {
    const hour = inAnHour(timezone.value)
    const tomorrow = tomorrowAtNine(timezone.value)
    return [
        { label: ctrans("In an hour (:time)", { time: hour.slice(11) }), command: () => emit("pick", hour) },
        { label: ctrans("Tomorrow at 9:00 (:day)", { day: wallClockLabel(tomorrow).split(",")[0] }), command: () => emit("pick", tomorrow) },
        { label: ctrans("Choose a time…"), command: () => (choosing.value = true) },
    ]
}

const pick = (local: string) => emit("pick", local)

const open = (event: Event) => {
    model.value = items()
    menu.value?.toggle(event)
}

defineExpose({ close: () => (choosing.value = false) })
</script>

<template>
    <span class="inline-flex">
        <button
            type="button"
            class="inline-flex items-center gap-x-1 whitespace-nowrap rounded px-1.5 py-0.5 text-xs text-gray-600 hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="disabled || busy"
            :title="disabledReason ?? ctrans('Send this message later')"
            aria-haspopup="true"
            @click="open">
            <FontAwesomeIcon icon="fal fa-clock" fixed-width aria-hidden="true" />
            {{ ctrans("Send later") }}
        </button>
        <Menu ref="menu" :model="model" popup />
        <ScheduleTimeDialog v-model:visible="choosing" :header="ctrans('Send later')" :confirm-label="ctrans('Schedule message')" :error="error" :busy="busy" @pick="pick" />
    </span>
</template>
