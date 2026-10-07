<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faEye } from "@fal"
import B2BDashboardInsights from "@/Components/Retina/Dashboard/B2BDashboardInsights.vue"
import { ctrans } from "@/Composables/useTrans"
import { setColorStyleRootByEl } from "@/Composables/useApp"
import { onMounted, ref } from "vue"

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: Record<string, any> | null
}>()

const websiteTheme = ref<HTMLElement | null>(null)

onMounted(() => {
    if (websiteTheme.value && props.data?.theme_colors?.length) {
        setColorStyleRootByEl(websiteTheme.value, props.data.theme_colors)
    }
})
</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2 text-sm text-sky-800">
            <FontAwesomeIcon :icon="faEye" fixed-width aria-hidden="true" />
            {{ ctrans("This is exactly what the customer sees on their dashboard. Their buttons are shown but disabled here.") }}
        </div>
        <div ref="websiteTheme">
            <B2BDashboardInsights v-if="data" :insights="data" read-only />
        </div>
    </div>
</template>
