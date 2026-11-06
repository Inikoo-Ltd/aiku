<script setup lang="ts">
import { computed, ref } from "vue"
import Select from "primevue/select"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faTimes, faClock } from "@fal"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        minuteStep?: number
    }
}>()

props.form[props.fieldName] = [...(props.form[props.fieldName] ?? [])]
const startTimes = computed<string[]>(() => props.form[props.fieldName])

const errorMessages = computed<string[]>(() => Object.entries(props.form.errors ?? {})
    .filter(([key]) => key === props.fieldName || key.startsWith(`${props.fieldName}.`))
    .map(([, message]) => message as string))

const minuteStep = props.fieldData?.minuteStep ?? 15
const timeOptions: string[] = []
for (let minutes = 0; minutes < 24 * 60; minutes += minuteStep) {
    timeOptions.push(`${String(Math.floor(minutes / 60)).padStart(2, "0")}:${String(minutes % 60).padStart(2, "0")}`)
}

const availableOptions = computed(() => timeOptions.filter(time => !startTimes.value.includes(time)))
const newTime = ref<string | null>(null)

const addTime = () => {
    if (!newTime.value || startTimes.value.includes(newTime.value)) {
        return
    }
    startTimes.value.push(newTime.value)
    startTimes.value.sort()
    newTime.value = null
}

const removeTime = (index: number) => {
    startTimes.value.splice(index, 1)
}

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)]"
const selectPt = { label: { class: "!py-1 !text-sm" } }
</script>

<template>
    <div class="space-y-3">
        <div v-if="startTimes.length" class="flex flex-wrap gap-2">
            <span v-for="(time, index) in startTimes" :key="time"
                class="inline-flex items-center gap-1 rounded-md border border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] py-0.5 pl-2 pr-1 text-sm font-medium tabular-nums text-gray-700">
                {{ time }}
                <button type="button" class="flex h-5 w-5 items-center justify-center rounded text-gray-400 hover:bg-white hover:text-red-500"
                    :aria-label="ctrans('Remove :time', { time })"
                    @click="removeTime(index)">
                    <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                </button>
            </span>
        </div>
        <div v-else class="flex items-center gap-2 text-sm text-gray-400">
            <FontAwesomeIcon :icon="faClock" fixed-width aria-hidden="true" />
            {{ ctrans("No start times, appointments follow one another from the opening time.") }}
        </div>

        <div class="flex items-center gap-2">
            <Select v-model="newTime" :options="availableOptions" :placeholder="ctrans('Pick a time')"
                :class="fieldFocusClass" :pt="selectPt" class="w-40" :aria-label="ctrans('Start time')" />
            <Button type="tertiary" size="s" :label="ctrans('Add start time')" :disabled="!newTime" @click="addTime">
                <template #icon>
                    <FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>

        <p v-for="message in errorMessages" :key="message" class="text-sm text-red-600">
            {{ message }}
        </p>
    </div>
</template>
