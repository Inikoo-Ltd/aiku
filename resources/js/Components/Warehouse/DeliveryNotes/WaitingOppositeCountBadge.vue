<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
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
            ? trans(':count items waiting for warehouse', { count: props.count })
            : trans(':count items waiting for CRM', { count: props.count })"
        :class="[
            'inline-flex items-center gap-x-1 rounded border px-1.5 py-0.5 text-xs font-semibold tabular-nums',
            type === 'warehouse'
                ? 'border-yellow-400 bg-yellow-500/20 text-yellow-700 hover:bg-yellow-500/30'
                : 'border-purple-400 bg-purple-500/20 text-purple-700 hover:bg-purple-500/30',
        ]"
    >
        <FontAwesomeIcon icon="fal fa-hourglass-start" fixed-width aria-hidden="true" />
        {{ props.count }}
    </Link>
</template>
