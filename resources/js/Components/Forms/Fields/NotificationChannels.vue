<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCircle, faCheckDouble, faCommentLines, faAt, faQuestionCircle } from "@fal"
import { faCheckCircle as fasCheckCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCircle, faCheckDouble, faCommentLines, faAt, faQuestionCircle, fasCheckCircle)

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
        events: { value: string, label: string, icon?: string }[]
        channels: { value: string, label: string, available?: boolean, unavailable_reason?: string }[]
    }
}>()

const isChosen = (event: string, channel: string): boolean => (props.form[props.fieldName]?.[event] ?? []).includes(channel)

const toggle = (event: string, channel: string) => {
    const current: string[] = props.form[props.fieldName]?.[event] ?? []
    props.form[props.fieldName] = {
        ...props.form[props.fieldName],
        [event]: isChosen(event, channel) ? current.filter(value => value !== channel) : [...current, channel],
    }
}
</script>

<template>
    <div class="w-full">
        <div
            v-for="event in fieldData.events"
            :key="event.value"
            class="grid grid-cols-1 sm:grid-cols-2 gap-x-1.5 px-2 items-center border-b border-gray-200 even:bg-gray-50"
        >
            <div class="flex items-center gap-x-1.5 py-3 text-gray-700">
                <FontAwesomeIcon v-if="event.icon" :icon="event.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                {{ event.label }}
            </div>

            <div class="flex flex-wrap items-center gap-x-4 pb-2 sm:pb-0">
                <button
                    v-for="channel in fieldData.channels"
                    :key="channel.value"
                    type="button"
                    role="checkbox"
                    :aria-checked="channel.available !== false && isChosen(event.value, channel.value)"
                    :aria-disabled="channel.available === false"
                    v-tooltip="channel.available === false ? channel.unavailable_reason : undefined"
                    @click.prevent="channel.available !== false && toggle(event.value, channel.value)"
                    class="group flex items-center gap-x-1.5 rounded-md py-2 px-3 font-medium cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 "
                    :class="channel.available === false ? 'cursor-not-allowed opacity-40' : ''"
                >
                    <FontAwesomeIcon v-if="channel.available !== false && isChosen(event.value, channel.value)" icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-else icon="fal fa-circle" class="text-gray-400 group-hover:text-gray-700" fixed-width aria-hidden="true" />
                    <span class="text-gray-700">{{ channel.label }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
