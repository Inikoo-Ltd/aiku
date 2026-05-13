<script setup lang="ts">
import { getIrisAnnouncementComponent } from "@/Iris/Composables/getIrisComponents"
import { inject, provide, computed, ref, onMounted, onBeforeUnmount } from "vue"

const props = defineProps<{
    data: {
        container_properties: {}
        schedule_at?: string | null
        schedule_finish_at?: string | null
        settings: {
            target_pages: {
                type: 'all' | 'specific'
            }
            target_users: {
                auth_state: 'all' | 'logged_in' | 'logged_out'
            }
        }
    }
}>()

const layout = inject("layout", {})
const isLoggedIn = computed(() => {
    return layout.iris?.is_logged_in
})
provide("isPreviewLoggedIn", isLoggedIn)

const now = ref(Date.now())
let tickTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
    const hasSchedule = props.data?.schedule_at || props.data?.schedule_finish_at
    if (!hasSchedule) return
    tickTimer = setInterval(() => { now.value = Date.now() }, 20_000)
})

onBeforeUnmount(() => {
    if (tickTimer) clearInterval(tickTimer)
})

const isWithinSchedule = computed(() => {
    const startStr = props?.data?.schedule_at
    const finishStr = props?.data?.schedule_finish_at

    const start = startStr ? new Date(startStr).getTime() : null
    const finish = finishStr ? new Date(finishStr).getTime() : null

    if (start !== null && !Number.isNaN(start) && now.value < start) return false
    if (finish !== null && !Number.isNaN(finish) && now.value >= finish) return false
    return true
})

const shouldShowAnnouncement = computed(() => {
    if (!isWithinSchedule.value) return false

    const authState = props?.data?.settings?.target_users?.auth_state
    if (authState === 'all') return true
    if (authState === 'logged_in') return isLoggedIn.value
    if (authState === 'logged_out') return !isLoggedIn.value
    return false
})

</script>

<template>
    <component
        v-if="shouldShowAnnouncement"
        :is="getIrisAnnouncementComponent(data?.template_code)"
        :announcementData="data"
    />
</template>