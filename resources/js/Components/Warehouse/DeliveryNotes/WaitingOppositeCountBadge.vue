<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHourglassStart } from "@fal"
import { trans } from "laravel-vue-i18n"

library.add(faHourglassStart)

const props = defineProps<{
    count: number
    type: "warehouse" | "crm"
    href: string
}>()
</script>

<template>
    <Link
        :href="href"
        v-tooltip="type === 'warehouse'
            ? ctrans(':count items waiting for warehouse', { count: props.count })
            : ctrans(':count items waiting for CRM', { count: props.count })"
        :class="[
            'inline-flex items-center gap-x-1 rounded  px-1.5 py-0 text-xs font-semibold tabular-nums ',
            type === 'warehouse'
                ? 'bg-amber-400/20 text-amber-600 hover:bg-amber-500/30'
                : 'bg-purple-500/20 text-purple-700 hover:bg-purple-500/30',
        ]"
    >

        {{ props.count }}
    </Link>
</template>
