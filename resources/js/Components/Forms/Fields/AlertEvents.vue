<script setup lang='ts'>
import Checkbox from 'primevue/checkbox'
import { ctrans } from '@/Composables/useTrans'
import { ALERT_EVENT_WAYS, type AlertEvent, type AlertWay } from '@/Composables/useNotificationSound'

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
    }
}>()

const events: { key: AlertEvent, label: string, hint: string }[] = [
    { key: 'customer_message', label: ctrans('New customer message'), hint: ctrans('A customer writes on the website, WhatsApp or by email') },
    { key: 'assigned', label: ctrans('Assigned to me'), hint: ctrans('A colleague or a supervisor gives you a conversation') },
    { key: 'colleague', label: ctrans('Colleague messages'), hint: ctrans('A colleague writes to you in the staff chat') },
    { key: 'waiting', label: ctrans('Customer still waiting'), hint: ctrans('Again every 30 seconds while a chat is unanswered') },
    { key: 'ticket', label: ctrans('Tickets'), hint: ctrans('A ticket is raised, answered, resolved or needs your QA') },
]

const ways: { key: AlertWay, label: string }[] = [
    { key: 'popup', label: ctrans('Pop-up') },
    { key: 'desktop', label: ctrans('Desktop') },
    { key: 'sound', label: ctrans('Sound') },
]

const isOn = (event: AlertEvent, way: AlertWay) => props.form[props.fieldName]?.[event]?.[way] !== false

const setOn = (event: AlertEvent, way: AlertWay, on: boolean) => {
    const current = props.form[props.fieldName] ?? {}

    props.form[props.fieldName] = { ...current, [event]: { ...(current[event] ?? {}), [way]: on } }
}

const checkboxPt = {
    box: ({ context }: { context: { checked: boolean } }) => ({
        class: context.checked ? '!border-[--app-accent] !bg-[--app-accent]' : '!border-gray-300',
    }),
    icon: { class: '!text-[--app-accent-text]' },
}
</script>

<template>
    <div class="w-full text-sm">
        <table class="w-full rounded-lg border border-gray-200">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-600">
                <tr>
                    <th scope="col" class="px-4 py-2.5 text-left">{{ ctrans('Event') }}</th>
                    <th v-for="way in ways" :key="way.key" scope="col" class="w-20 px-2 py-2.5 text-center">{{ way.label }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="event in events" :key="event.key" class="border-t border-gray-100">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">{{ event.label }}</div>
                        <div class="text-xs text-gray-400">{{ event.hint }}</div>
                    </td>
                    <td v-for="way in ways" :key="way.key" class="px-2 py-3 text-center">
                        <Checkbox v-if="ALERT_EVENT_WAYS[event.key].includes(way.key)" binary
                            :input-id="`alert-${event.key}-${way.key}`" :aria-label="`${event.label}: ${way.label}`"
                            :model-value="isOn(event.key, way.key)" :pt="checkboxPt"
                            @update:model-value="(on: boolean) => setOn(event.key, way.key, on)" />
                        <span v-else class="text-gray-300" :aria-label="ctrans('Not available')">–</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
