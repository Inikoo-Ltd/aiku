<script setup lang='ts'>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPlay } from '@fal'
import { ctrans } from '@/Composables/useTrans'
import { ALERT_SOUNDS, alertSoundLabels, chosenAlertSound, playChosenSound, type AlertSound, type AlertSoundKind } from '@/Composables/useNotificationSound'

library.add(faPlay)

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
    }
}>()

const kinds: { key: AlertSoundKind, label: string }[] = [
    { key: 'chat', label: ctrans('Website chat') },
    { key: 'whatsapp', label: ctrans('WhatsApp') },
    { key: 'email', label: ctrans('Email') },
    { key: 'colleague', label: ctrans('Colleague messages') },
]

const labels = alertSoundLabels()

const chosen = (kind: AlertSoundKind): AlertSound => props.form[props.fieldName]?.[kind] ?? chosenAlertSound(kind)

const preview = (sound: AlertSound) => playChosenSound(sound, ctrans('You have a message'))

const choose = (kind: AlertSoundKind, sound: AlertSound) => {
    props.form[props.fieldName] = { ...Object.fromEntries(kinds.map((row) => [row.key, chosen(row.key)])), [kind]: sound }
    preview(sound)
}
</script>

<template>
    <div class="w-full max-w-md space-y-2">
        <div v-for="kind in kinds" :key="kind.key" class="flex items-center gap-x-3">
            <span class="w-40 shrink-0 text-sm text-gray-700">{{ kind.label }}</span>
            <select
                :value="chosen(kind.key)"
                class="flex-1 rounded-md border-gray-300 py-1.5 text-sm focus:border-gray-500 focus:ring-0"
                @change="choose(kind.key, ($event.target as HTMLSelectElement).value as AlertSound)">
                <option v-for="sound in ALERT_SOUNDS" :key="sound" :value="sound">{{ labels[sound] }}</option>
            </select>
            <button
                type="button"
                class="h-8 w-8 shrink-0 rounded-md border border-gray-300 text-gray-500 hover:text-gray-800 hover:border-gray-500"
                :aria-label="ctrans('Play')"
                @click="preview(chosen(kind.key))">
                <FontAwesomeIcon icon="fal fa-play" fixed-width aria-hidden="true" />
            </button>
        </div>
    </div>
</template>
