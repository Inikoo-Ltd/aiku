<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-26 14:10
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

const props = defineProps<{
    mergedInto: {
        code: string
        name: string
        merged_by: string | null
        merged_at: string | null
        route: routeType | null
    }
}>()

const mergedDetail = computed(() => {
    const { merged_by, merged_at } = props.mergedInto
    const mergedBy = merged_by ?? ctrans("system")
    if (merged_at) {
        return ctrans("Merged by :user on :date", { user: mergedBy, date: useFormatTime(merged_at) })
    }
    return ctrans("Merged by :user", { user: mergedBy })
})
</script>

<template>
    <span v-tooltip="mergedDetail" class="whitespace-nowrap text-xs text-gray-500">
        {{ ctrans("Merged into") }}
        <Link v-if="mergedInto.route" :href="route(mergedInto.route.name, mergedInto.route.parameters)" class="primaryLink font-medium">{{ mergedInto.code }} ({{ mergedInto.name }})</Link>
        <span v-else class="font-medium text-gray-700">{{ mergedInto.code }} ({{ mergedInto.name }})</span>
    </span>
</template>
