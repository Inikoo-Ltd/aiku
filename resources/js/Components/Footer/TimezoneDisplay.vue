<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { formatInTimeZone } from 'date-fns-tz'

const times = ref({
    Slovakia: '',
    UK: '',
    Spain: '',
    KL: '',
    Bali: '',
    India: '',
    China: '',
})

const timezones = {
    Slovakia: 'Europe/Bratislava',
    UK: 'Europe/London',
    Spain: 'Europe/Madrid',
    KL: 'Asia/Kuala_Lumpur',
    Bali: 'Asia/Makassar',
    India: 'Asia/Kolkata',
    China: 'Asia/Shanghai',
}

const updateTimes = () => {
    const now = new Date()
    for (const [country, timezone] of Object.entries(timezones)) {
        times.value[country] = formatInTimeZone(now, timezone, 'HH:mm')
    }
}

onMounted(() => {
    updateTimes()
    setInterval(updateTimes, 60000) // Update every minute
})

onBeforeUnmount(() => {
    clearInterval(updateTimes)
});
</script>

<template>
    <div class="flex gap-x-6 text-white/70">
        <p v-for="(time, country) in times" :key="country" class="tabular-nums">
            <strong>{{ country }}:</strong> {{ time }}
        </p>
    </div>
</template>
