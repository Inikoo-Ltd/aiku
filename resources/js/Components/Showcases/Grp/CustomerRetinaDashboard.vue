<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faEye } from "@fal"
import B2BDashboardInsights from "@/Components/Retina/Dashboard/B2BDashboardInsights.vue"
import { ctrans } from "@/Composables/useTrans"

defineOptions({ inheritAttrs: false })

defineProps<{
    data?: Record<string, any> | null
}>()
</script>

<template>
    <div class="p-4 sm:p-6">
        <div class="mb-4 flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 py-2 text-sm text-sky-800">
            <FontAwesomeIcon :icon="faEye" fixed-width aria-hidden="true" />
            {{ ctrans("This is exactly what the customer sees on their dashboard. Their buttons are shown but disabled here.") }}
        </div>
        <B2BDashboardInsights v-if="data" :insights="data" read-only />
    </div>
</template>
