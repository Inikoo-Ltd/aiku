<script setup lang='ts'>
import { reactive } from 'vue'
import SoundPicker from '@/Components/Forms/Fields/SoundPicker.vue'
import { ctrans } from '@/Composables/useTrans'
import { ALERT_SOUNDS, alertSoundLabels, chosenAlertSound, playChosenSound, type AlertSound, type AlertSoundKind } from '@/Composables/useNotificationSound'

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
    }
}>()

const kinds: { key: AlertSoundKind, label: string, hint: string }[] = [
    { key: 'chat', label: ctrans('Website chat'), hint: ctrans('A customer writes on the website') },
    { key: 'whatsapp', label: ctrans('WhatsApp'), hint: ctrans('A customer writes on WhatsApp') },
    { key: 'email', label: ctrans('Email'), hint: ctrans('A customer email arrives') },
    { key: 'colleague', label: ctrans('Colleague messages'), hint: ctrans('A colleague writes to you') },
    { key: 'waiting', label: ctrans('Customer still waiting'), hint: ctrans('Rings again every 30 seconds while a chat is unanswered') },
    { key: 'ticket', label: ctrans('Ticket resolved'), hint: ctrans('A ticket you reported is marked done') },
]

const labels = alertSoundLabels()

const emojis: Record<AlertSound, string> = {
    chime: '🎐',
    bells: '🔔',
    dingdong: '🛎️',
    pop: '🫧',
    marimba: '🎵',
    submarine: '📡',
    voice: '🗣️',
    bird: '🐦',
    boing: '🪀',
    fart: '💨',
    triumph: '🏆',
    gong: '🥁',
    sparkle: '✨',
    knock: '🚪',
    genie: '🧞',
    silent: '🔇',
}

const audibleSounds = ALERT_SOUNDS.filter((sound) => sound !== 'silent')

const soundBeforeMute = reactive<Partial<Record<AlertSoundKind, AlertSound>>>({})

const chosen = (kind: AlertSoundKind): AlertSound => props.form[props.fieldName]?.[kind] ?? chosenAlertSound(kind)

const isMuted = (kind: AlertSoundKind) => chosen(kind) === 'silent'

const shownSound = (kind: AlertSoundKind): AlertSound => isMuted(kind) ? (soundBeforeMute[kind] ?? 'chime') : chosen(kind)

const preview = (sound: AlertSound) => playChosenSound(sound, ctrans('You have a message'))

const choose = (kind: AlertSoundKind, sound: AlertSound) => {
    props.form[props.fieldName] = { ...Object.fromEntries(kinds.map((row) => [row.key, chosen(row.key)])), [kind]: sound }
}

const setMuted = (kind: AlertSoundKind, muted: boolean) => {
    if (muted) {
        soundBeforeMute[kind] = chosen(kind)
    }
    choose(kind, muted ? 'silent' : shownSound(kind))
}
</script>

<template>
    <div class="w-full text-sm">
        <section class="rounded-lg border border-gray-200">
            <header class="rounded-t-lg border-b border-gray-200 bg-gray-50 px-4 py-2.5 font-semibold text-gray-900">
                {{ ctrans('Chat') }}
            </header>

            <div v-for="kind in kinds" :key="kind.key" class="flex items-center gap-x-4 border-b border-gray-100 px-4 py-3 last:border-b-0">
                <div class="min-w-0 flex-1">
                    <div class="font-medium" :class="isMuted(kind.key) ? 'text-gray-500' : 'text-gray-900'">{{ kind.label }}</div>
                    <div class="text-xs text-gray-400">{{ kind.hint }}</div>
                </div>

                <SoundPicker
                    :sound="shownSound(kind.key)"
                    :muted="isMuted(kind.key)"
                    :sounds="audibleSounds"
                    :labels="labels"
                    :emojis="emojis"
                    :label="kind.label"
                    @update:sound="(sound) => choose(kind.key, sound as AlertSound)"
                    @update:muted="(muted) => setMuted(kind.key, muted)"
                    @play="preview(shownSound(kind.key))" />
            </div>
        </section>
    </div>
</template>
