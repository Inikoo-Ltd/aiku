<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import { Popover } from "primevue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import SlackNodes, { type SlackNode } from "@/Components/Messaging/Slack/SlackNodes.vue"

const props = defineProps<{ url: string; channelLinks: Record<string, number> }>()

const popover = ref()
const versions = ref<{ id: number; replaced_at: string | null; nodes: SlackNode[] }[] | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const toggle = async (event: Event) => {
    popover.value.toggle(event)
    if (versions.value || loading.value) return
    loading.value = true
    error.value = null
    try {
        const { data } = await axios.get(props.url)
        versions.value = data.versions
    } catch (e: any) {
        error.value = e?.response?.status === 404 ? ctrans("These versions are not available") : ctrans("The versions could not be loaded, try again")
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <button type="button" class="ml-1 text-xxs text-gray-400 hover:text-gray-600 hover:underline" @click="toggle">({{ ctrans("edited") }})</button>
    <Popover ref="popover" :pt="{ root: { class: 'max-w-sm' } }">
        <div class="w-80 max-w-full text-sm">
            <div class="mb-2 text-xs font-semibold text-gray-600">{{ ctrans("Earlier versions") }}</div>
            <LoadingIcon v-if="loading" />
            <p v-else-if="error" class="text-xs text-red-600">{{ error }}</p>
            <p v-else-if="versions && !versions.length" class="text-xs text-gray-500">{{ ctrans("No earlier versions are kept") }}</p>
            <ul v-else-if="versions" class="max-h-72 space-y-2 overflow-y-auto">
                <li v-for="version in versions" :key="version.id" class="border-l-2 border-gray-200 pl-2">
                    <div class="text-xxs text-gray-400">{{ ctrans("Replaced :time", { time: useFormatTime(version.replaced_at, { formatTime: "short-datetime" }) }) }}</div>
                    <div class="break-words text-gray-800"><SlackNodes :nodes="version.nodes" :channel-links="channelLinks" /></div>
                </li>
            </ul>
        </div>
    </Popover>
</template>
