<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import axios from "axios"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowLeft, faLightbulbOn, faPalette } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import ChatTheme from "@/Components/Forms/Fields/ChatTheme.vue"
import ChatWallpaperPicker from "@/Components/Messaging/ChatWallpaperPicker.vue"
import ChatWallpaperPreview from "@/Components/Messaging/ChatWallpaperPreview.vue"
import type { ChatWallpaperCatalogue } from "@/Components/Messaging/chatWallpaper"
import { applyChatTheme } from "@/Composables/useChatThemes"

const props = defineProps<{
    form: any
    fieldName: string
    options: ChatWallpaperCatalogue
    fieldData: { noSaveButton?: boolean; sidebar_colour?: string; information?: string }
    submit?: () => void
}>()

if (!props.form[props.fieldName]) {
    props.form[props.fieldName] = { pattern: "none", colour: "none", style: "rows" }
}
if (!props.form[props.fieldName].style) {
    props.form[props.fieldName].style = "rows"
}

const styleOptions = [
    { value: "rows", label: ctrans("Rows"), help: ctrans("Name and text in lines, one message under another") },
    { value: "bubbles", label: ctrans("Bubbles"), help: ctrans("Each message in its own bubble, yours in your theme colour") },
]

const value = computed(() => props.form[props.fieldName])

watch(() => JSON.stringify(value.value), (now, before) => {
    if (props.fieldData.noSaveButton && now !== before) props.submit?.()
})

const sidebar = reactive({ chat_theme: props.fieldData.sidebar_colour ?? "classic" })
const sidebarError = ref<string | null>(null)
let savedSidebar = sidebar.chat_theme
const saveSidebar = () => {
    sidebarError.value = null
    const chosen = sidebar.chat_theme
    axios.patch(route("grp.models.profile.update"), { chat_theme: chosen })
        .then(() => (savedSidebar = chosen))
        .catch(() => {
            sidebar.chat_theme = savedSidebar
            applyChatTheme(savedSidebar)
            sidebarError.value = ctrans("The sidebar colour could not be saved. Try again.")
        })
}


const previewWallpaper = computed(() => ({
    pattern: value.value.pattern === "none" ? null : value.value.pattern,
    colour: props.options.colours.find((option) => option.key === value.value.colour)?.hex ?? null,
}))
</script>

<template>
    <div class="flex w-full flex-col gap-6 lg:flex-row">
        <div class="min-w-0 flex-1 space-y-6">
            <div>
                <div class="mb-2 text-sm font-medium text-gray-500">{{ ctrans("Sidebar colour") }}</div>
                <ChatTheme :form="sidebar" field-name="chat_theme" :field-data="{ noSaveButton: true }" :submit="saveSidebar" />
                <p v-if="sidebarError" class="mt-1 text-xs text-red-600">{{ sidebarError }}</p>
            </div>
            <div>
                <div class="mb-2 text-sm font-medium text-gray-500">{{ ctrans("Wallpaper") }}</div>
                <p v-if="fieldData.information" class="-mt-1 mb-2 text-xs text-gray-400">{{ fieldData.information }}</p>
                <ChatWallpaperPicker v-model:pattern="value.pattern" v-model:colour="value.colour" :catalogue="options" show-usage />
            </div>
            <div>
                <div class="mb-1.5 text-xs font-medium text-gray-500">{{ ctrans("Message style") }}</div>
                <SegmentedToggle v-model="value.style" :options="styleOptions" :aria-label="ctrans('Message style')" />
            </div>
        </div>
        <div class="w-full shrink-0 border-gray-200 lg:w-96 lg:border-l lg:pl-6">
            <div class="lg:sticky lg:top-4">
                <div class="mb-2 text-sm font-medium text-gray-500">{{ ctrans("Preview") }}</div>
                <ChatWallpaperPreview :wallpaper="previewWallpaper" :message-style="value.style" />
                <Link :href="route('grp.chat.staff.feed')" class="mt-3 inline-block">
                    <Button type="tertiary" :label="ctrans('Go to Verba')">
                        <template #icon><FontAwesomeIcon :icon="faArrowLeft" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
                    </Button>
                </Link>
                <p class="mt-6 flex gap-2 rounded-lg bg-[var(--app-accent-soft)] p-3 text-xs leading-relaxed text-gray-700">
                    <FontAwesomeIcon :icon="faLightbulbOn" class="mt-0.5 shrink-0 text-amber-500" fixed-width aria-hidden="true" />
                    <span>
                        {{ ctrans("Tip: give any channel or chat its own wallpaper, so you know at a glance where you are writing. Look for the palette icon at the top right of the chat.") }}
                        <FontAwesomeIcon :icon="faPalette" class="ml-0.5 text-gray-600" fixed-width aria-hidden="true" />
                    </span>
                </p>
            </div>
        </div>
    </div>
</template>
