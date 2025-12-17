<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch, inject } from 'vue'
import { formatInTimeZone } from 'date-fns-tz'
import { layoutStructure } from '@/Composables/useLayoutStructure'


const layout = inject('layout', layoutStructure)

const times = ref<Record<string, string>>({})

const updateTimes = () => {
    const now = new Date()
    const updated: Record<string, string> = {}
    for (const tz of layout?.user?.settings?.timezones || []) {
        //  { "Asia/Makassar": "15:04" }
        updated[tz] = formatInTimeZone(now, tz, 'HH:mm')
    }
    times.value = updated
}

let intervalId: number | undefined

onMounted(() => {
    updateTimes()
    intervalId = window.setInterval(updateTimes, 60000)
})

onBeforeUnmount(() => {
    if (intervalId) {
        clearInterval(intervalId)
    }
});

watch(() => layout?.user?.settings?.timezones, (newVal) => {
    updateTimes()
})
</script>

<template>
    <div v-if="layout?.user?.settings?.timezones?.length" class="flex gap-x-6 text-xs h-full items-center">
        <p v-for="(time, zone) in times" :key="zone" class="tabular-nums">
            {{ zone.split('/')[1] }}: {{ time }}
        </p>
    </div>
</template>
