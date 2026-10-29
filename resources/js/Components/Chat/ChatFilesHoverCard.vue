<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, nextTick, ref } from "vue"
import axios from "axios"
import Popover from "primevue/popover"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFile } from "@fal"
import { ctrans } from "@/Composables/useTrans"

interface ChatFile {
    id: number
    is_image: boolean
    thumbnail: { webp?: string; original?: string } | null
    file_name: string | null
    file_size: number | null
}

const MAX_PICTURES = 8
const MAX_FILES = 4

const popover = ref()
const isMounted = ref(false)
const currentUlid = ref("")
const isLoading = ref(false)
const cache = new Map<string, ChatFile[]>()
const items = ref<ChatFile[]>([])

// ponytail: same rule as the paperclip, an inline picture under 30 KB is taken for a signature logo
const shown = computed(() => items.value.filter((item) => !item.is_image || (item.file_size ?? 0) >= 30_000))
const pictures = computed(() => shown.value.filter((item) => item.is_image))
const files = computed(() => shown.value.filter((item) => !item.is_image))
const hiddenCount = computed(() => Math.max(0, pictures.value.length - MAX_PICTURES) + Math.max(0, files.value.length - MAX_FILES))

const formatSize = (bytes: number | null): string => {
    if (!bytes) {
        return ""
    }

    return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

const show = async (event: MouseEvent, sessionUlid: string) => {
    const target = event.currentTarget
    currentUlid.value = sessionUlid
    items.value = cache.get(sessionUlid) ?? []
    isMounted.value = true
    await nextTick()
    popover.value?.show(event, target)

    if (cache.has(sessionUlid)) {
        return
    }

    isLoading.value = true
    try {
        const response = await axios.get(route("grp.api.chats.sessions.files", [sessionUlid]))
        cache.set(sessionUlid, response.data?.data ?? [])
    } catch {
        cache.set(sessionUlid, [])
    } finally {
        if (currentUlid.value === sessionUlid) {
            items.value = cache.get(sessionUlid) ?? []
            isLoading.value = false
        }
    }
}

const hide = () => popover.value?.hide()

defineExpose({ show, hide })
</script>

<template>
    <Popover v-if="isMounted" ref="popover" class="pointer-events-none">
        <div class="w-80 p-1 text-xs text-gray-600">
            <div class="mb-2 text-sm font-semibold text-gray-800">{{ ctrans("Pictures and files") }}</div>

            <div v-if="isLoading" class="grid grid-cols-4 gap-1.5">
                <div v-for="n in 4" :key="n" class="aspect-square animate-pulse rounded bg-gray-100" />
            </div>

            <template v-else>
                <div v-if="pictures.length" class="grid grid-cols-4 gap-1.5">
                    <img v-for="picture in pictures.slice(0, MAX_PICTURES)" :key="picture.id"
                        :src="picture.thumbnail?.webp ?? picture.thumbnail?.original" :alt="picture.file_name ?? ''"
                        class="aspect-square w-full rounded border border-gray-200 object-cover" />
                </div>

                <div v-if="files.length" class="space-y-1" :class="{ 'mt-2': pictures.length }">
                    <div v-for="file in files.slice(0, MAX_FILES)" :key="file.id" class="flex items-center gap-2">
                        <FontAwesomeIcon :icon="faFile" class="shrink-0 text-gray-400" fixed-width />
                        <span class="min-w-0 truncate text-gray-700">{{ file.file_name }}</span>
                        <span class="ml-auto shrink-0 whitespace-nowrap text-gray-400">{{ formatSize(file.file_size) }}</span>
                    </div>
                </div>

                <div v-if="!shown.length" class="text-gray-400">{{ ctrans("Only signature pictures") }}</div>

                <div class="mt-2 border-t border-gray-100 pt-1.5 text-gray-400">
                    <template v-if="hiddenCount">{{ ctrans(":count more", { count: String(hiddenCount) }) }} · </template>{{ ctrans("Click to see them all") }}
                </div>
            </template>
        </div>
    </Popover>
</template>
