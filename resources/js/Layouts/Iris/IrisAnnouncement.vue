<script setup lang="ts">
import { getIrisAnnouncementComponent } from "@/Composables/getIrisComponents"
import { inject, provide, computed } from "vue"

const props = defineProps<{
    data: {
        container_properties: {}
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

const shouldShowAnnouncement = computed(() => {
    const authState = props.data.settings.target_users.auth_state
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