<script setup lang="ts">
import { getIrisAnnouncementComponent } from "@/Iris/Composables/getIrisComponents"
import { getStyles } from "@/Composables/styles"
import { inject, provide, computed } from "vue"

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

const reserveStyle = computed(() => {
    const height = getStyles(props.data?.container_properties)?.height
    return height ? { minHeight: height } : {}
})

</script>

<template>
    <div
        class="iris-announcement-reserve"
        :style="reserveStyle"
    >
        <component
            :is="getIrisAnnouncementComponent(data?.template_code)"
            :announcementData="data"
        />
    </div>
</template>