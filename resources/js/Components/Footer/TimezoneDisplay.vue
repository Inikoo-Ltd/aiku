<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import { formatInTimeZone } from 'date-fns-tz'
import { trans } from 'laravel-vue-i18n'
import { layoutStructure } from '@/Composables/useLayoutStructure'


const layout = inject('layout', layoutStructure)

const now = ref(new Date())

/** IANA names nobody recognises, shown as the places we actually work from. */
const zoneNicknames: Record<string, string> = {
    'Asia/Makassar': 'KL/Bali',
    'Asia/Kuala_Lumpur': 'KL/Bali',
    'Europe/Bratislava': 'Slovakia',
    'Europe/Madrid': 'Spain',
}

const zoneLabel = (zone: string) => zoneNicknames[zone] ?? zone.split('/').pop()?.replace(/_/g, ' ') ?? zone

const ownZone = computed<string | null>(() => layout?.user?.timezone ?? null)

const otherZones = computed<string[]>(() =>
    (layout?.group?.timezones || []).filter((zone: string) => zone !== ownZone.value)
)

const ownTime = computed(() => (ownZone.value ? formatInTimeZone(now.value, ownZone.value, 'HH:mm') : null))

const times = computed<Record<string, string>>(() =>
    Object.fromEntries(otherZones.value.map((zone) => [zone, formatInTimeZone(now.value, zone, 'HH:mm')]))
)

let intervalId: number | undefined

const tick = () => {
    now.value = new Date()
}

onMounted(() => {
    tick()
    intervalId = window.setInterval(tick, 60000)
})

onBeforeUnmount(() => {
    if (intervalId) {
        clearInterval(intervalId)
    }
});

watch(() => layout?.group?.timezones, tick)
</script>

<template>
    <div v-if="ownZone || otherZones.length" class="flex gap-x-6 text-xs h-full items-center">
        <p v-if="ownZone" class="tabular-nums text-slate-200"
            v-tooltip="trans('Your timezone') + ': ' + ownZone">
            {{ zoneLabel(ownZone) }}: {{ ownTime }}
        </p>
        <p v-for="(time, zone) in times" :key="zone" class="tabular-nums" v-tooltip="zone">
            {{ zoneLabel(zone as string) }}: {{ time }}
        </p>
    </div>
</template>
