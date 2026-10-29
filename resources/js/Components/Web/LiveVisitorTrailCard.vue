<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 30 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { nextTick, ref } from "vue"
import axios from "axios"
import Popover from "primevue/popover"
import { ctrans } from "@/Composables/useTrans"
import { pagePath, shortDuration } from "@/Composables/useLiveVisitors"

interface TrailEntry {
    url?: string
    title?: string
    page_type?: string
    at: number
}

const popover = ref()
const isMounted = ref(false)
const currentSession = ref("")
const isLoading = ref(false)
const entries = ref<TrailEntry[]>([])

// Time on a page runs until the next page was opened; the last one is still open now.
const timeOn = (index: number) =>
    shortDuration(entries.value[index].at, (entries.value[index + 1]?.at ?? Date.now() / 1000) * 1000)

const clockTime = (at: number) =>
    new Date(at * 1000).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })

const show = async (event: MouseEvent, websiteId: number, sessionId: string) => {
    const target = event.currentTarget
    currentSession.value = sessionId
    entries.value = []
    isMounted.value = true
    await nextTick()
    popover.value?.show(event, target)

    isLoading.value = true
    try {
        const response = await axios.get(route("grp.json.website.live_visitor_trail", [websiteId, sessionId]))
        if (currentSession.value === sessionId) {
            entries.value = response.data ?? []
        }
    } catch {
        entries.value = []
    } finally {
        isLoading.value = false
    }
}

const hide = () => popover.value?.hide()

defineExpose({ show, hide })
</script>

<template>
    <Popover v-if="isMounted" ref="popover" class="pointer-events-none">
        <div class="w-80 p-1 text-xs text-gray-600">
            <div class="mb-2 text-sm font-semibold text-gray-800">{{ ctrans("Journey") }}</div>

            <div v-if="isLoading" class="space-y-1.5">
                <div v-for="n in 4" :key="n" class="h-4 animate-pulse rounded bg-gray-100" />
            </div>

            <template v-else>
                <ol v-if="entries.length" class="space-y-1">
                    <li v-for="(entry, index) in entries" :key="entry.at + (entry.url ?? '')" class="flex items-center gap-2">
                        <span class="shrink-0 tabular-nums text-gray-400">{{ clockTime(entry.at) }}</span>
                        <span class="min-w-0 truncate" :class="index === entries.length - 1 ? 'font-medium text-gray-900' : 'text-gray-700'">
                            {{ pagePath({ url: entry.url, page: entry.title }) }}
                        </span>
                        <span class="ml-auto shrink-0 whitespace-nowrap tabular-nums text-gray-400">{{ timeOn(index) }}</span>
                    </li>
                </ol>

                <div v-else class="text-gray-400">{{ ctrans("No pages recorded yet") }}</div>

                <div class="mt-2 border-t border-gray-100 pt-1.5 text-gray-400">
                    {{ ctrans("Last 2 hours, up to 20 pages") }}
                </div>
            </template>
        </div>
    </Popover>
</template>
