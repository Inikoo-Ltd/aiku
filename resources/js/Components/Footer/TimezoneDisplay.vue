<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import { formatInTimeZone } from 'date-fns-tz'
import { trans } from 'laravel-vue-i18n'
import { layoutStructure } from '@/Composables/useLayoutStructure'


const layout = inject('layout', layoutStructure)

const now = ref(new Date())

const ownZone = computed<string | null>(() => layout?.user?.timezone ?? null)

const ownClock = computed(() =>
    ownZone.value
        ? {
              zone: ownZone.value,
              place: layout?.user?.timezone_place ?? ownZone.value,
              time: formatInTimeZone(now.value, ownZone.value, 'HH:mm'),
          }
        : null
)

const groupClocks = computed(() =>
    (layout?.group?.timezones || [])
        .filter((clock) => clock.timezone !== ownZone.value)
        .map((clock) => ({
            zone: clock.timezone,
            place: clock.place,
            time: formatInTimeZone(now.value, clock.timezone, 'HH:mm'),
        }))
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
    <div v-if="ownClock || groupClocks.length" class="flex gap-x-6 text-xs h-full items-center">
        <p v-if="ownClock" class="tabular-nums text-slate-200"
            v-tooltip="trans('Your timezone') + ': ' + ownClock.zone">
            {{ ownClock.place }}: {{ ownClock.time }}
        </p>
        <p v-for="clock in groupClocks" :key="clock.zone" class="tabular-nums" v-tooltip="clock.zone">
            {{ clock.place }}: {{ clock.time }}
        </p>
    </div>
</template>
