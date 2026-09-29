<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Fri, 25 Sep 2026 Malaga, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

const props = defineProps<{
    active: "command" | "analysis"
    organisation?: string | null
    family?: string | null
    search?: string | null
    fetching?: boolean
}>()

const sharedParams = computed<Record<string, string>>(() => {
    const params: Record<string, string> = {}
    if (props.organisation) params.organisation = props.organisation
    if (props.family) params.family = props.family
    if (props.search) params.search = props.search
    return params
})

const today = computed(() =>
    new Date().toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" })
)
</script>

<template>
    <div class="mx-4 mt-3 flex flex-wrap items-center justify-between gap-2">
        <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-xs">
            <Link
                :href="route('grp.goods.dashboard', sharedParams)"
                class="rounded-md px-3 py-1.5 font-medium transition-colors"
                :class="active === 'command' ? 'bg-[--app-accent] text-[--app-accent-text] shadow-sm' : 'text-gray-600 hover:bg-white hover:text-gray-900'"
            >
                {{ ctrans("Command View") }}
            </Link>
            <Link
                :href="route('grp.goods.analysis', sharedParams)"
                class="rounded-md px-3 py-1.5 font-medium transition-colors"
                :class="active === 'analysis' ? 'bg-[--app-accent] text-[--app-accent-text] shadow-sm' : 'text-gray-600 hover:bg-white hover:text-gray-900'"
            >
                {{ ctrans("Analysis View") }}
            </Link>
        </div>
        <div v-if="fetching" class="ml-2 mr-auto flex items-center gap-2 text-sm text-gray-500" role="status" aria-live="polite">
            <LoadingIcon class="text-[--app-accent]" />
            {{ ctrans("We're still fetching the data. Please wait") }}
        </div>
        <div class="text-xs text-gray-500">{{ today }}</div>
    </div>
</template>
