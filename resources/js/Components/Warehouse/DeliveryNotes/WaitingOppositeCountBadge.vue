<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { computed } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHourglassStart } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faHourglassStart)

const props = defineProps<{
    count: number
    type: "warehouse" | "crm"
    href: string
    ready?: number
}>()

const allReady = computed(() => props.type === "warehouse" && Number(props.ready) >= props.count)
</script>

<template>
    <Link
        :href="href"
        v-tooltip="allReady
            ? ctrans(':count waiting items now on the shelf, ready to pick', { count: props.count })
            : type === 'warehouse' && Number(props.ready) > 0
            ? ctrans(':count items waiting for warehouse, :ready of them ready to pick', { count: props.count, ready: props.ready })
            : type === 'warehouse'
            ? ctrans(':count items waiting for warehouse', { count: props.count })
            : ctrans(':count items waiting for CRM', { count: props.count })"
        :class="[
            'inline-flex items-center gap-x-1 rounded  px-1.5 py-0 text-xs font-semibold tabular-nums ',
            allReady
                ? 'bg-green-600/20 text-green-700 hover:bg-green-600/30'
                : type === 'warehouse'
                ? 'bg-amber-400/20 text-amber-600 hover:bg-amber-500/30'
                : 'bg-purple-500/20 text-purple-700 hover:bg-purple-500/30',
        ]"
    >

        {{ props.count }}
        <span v-if="allReady" class="border-l border-green-700/30 pl-1 font-medium whitespace-nowrap">{{ ctrans("Pick now") }}</span>
    </Link>
</template>
