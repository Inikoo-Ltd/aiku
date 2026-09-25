<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Fri, 25 Sep 2026 Malaga, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    active: "command" | "analysis"
    organisation?: string | null
    family?: string | null
    search?: string | null
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
    <div class="mx-4 mt-2 flex flex-wrap items-center justify-between gap-2">
        <div class="inline-flex rounded-md border border-gray-300 p-0.5 text-xs">
            <Link
                :href="route('grp.goods.dashboard', sharedParams)"
                class="rounded px-2.5 py-1 font-medium"
                :class="active === 'command' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
            >
                {{ ctrans("Command View") }}
            </Link>
            <Link
                :href="route('grp.goods.analysis', sharedParams)"
                class="rounded px-2.5 py-1 font-medium"
                :class="active === 'analysis' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
            >
                {{ ctrans("Analysis View") }}
            </Link>
        </div>
        <div class="text-xs text-gray-500">{{ today }}</div>
    </div>
</template>
