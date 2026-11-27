<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import ChatWallpaperPicker from "@/Components/Messaging/ChatWallpaperPicker.vue"
import ChatWallpaperPreview from "@/Components/Messaging/ChatWallpaperPreview.vue"
import type { ChatWallpaperCatalogue } from "@/Components/Messaging/chatWallpaper"

const props = defineProps<{
    form: any
    fieldName: string
    options: ChatWallpaperCatalogue
    fieldData: {}
}>()

if (!props.form[props.fieldName]) {
    props.form[props.fieldName] = { pattern: "none", colour: "none", mode: "same", style: "rows" }
}
if (!props.form[props.fieldName].style) {
    props.form[props.fieldName].style = "rows"
}

const styleOptions = [
    { value: "rows", label: ctrans("Rows"), help: ctrans("Name and text in lines, one message under another") },
    { value: "bubbles", label: ctrans("Bubbles"), help: ctrans("Each message in its own bubble, yours in your theme colour") },
]

const value = computed(() => props.form[props.fieldName])

const modeOptions = [
    { value: "same", label: ctrans("The same everywhere") },
    { value: "workplace", label: ctrans("By where the other person works"), help: ctrans("A direct chat takes a colour for the other person's organisation") },
    { value: "person", label: ctrans("A different colour for each person"), help: ctrans("Every direct chat gets its own soft colour, the same on every device") },
]

const previewWallpaper = computed(() => ({
    pattern: value.value.pattern === "none" ? null : value.value.pattern,
    colour: props.options.colours.find((option) => option.key === value.value.colour)?.hex ?? null,
}))
</script>

<template>
    <div class="flex w-full flex-col gap-4 lg:flex-row">
        <div class="min-w-0 flex-1 space-y-4">
            <ChatWallpaperPicker v-model:pattern="value.pattern" v-model:colour="value.colour" :catalogue="options" show-usage />
            <div>
                <div class="mb-1.5 text-xs font-medium text-gray-500">{{ ctrans("Message style") }}</div>
                <SegmentedToggle v-model="value.style" :options="styleOptions" :aria-label="ctrans('Message style')" />
            </div>
            <div>
                <div class="mb-1.5 text-xs font-medium text-gray-500">{{ ctrans("Direct chats") }}</div>
                <SegmentedToggle v-model="value.mode" :options="modeOptions" :aria-label="ctrans('Direct chats')" />
                <div class="mt-1 text-xxs text-gray-400">{{ ctrans("A wallpaper you set for one chat always wins") }}</div>
            </div>
        </div>
        <ChatWallpaperPreview :wallpaper="previewWallpaper" class="w-full shrink-0 lg:w-72" />
    </div>
</template>
