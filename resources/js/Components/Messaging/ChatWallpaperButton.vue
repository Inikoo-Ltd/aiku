<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { usePage } from "@inertiajs/vue3"
import axios from "axios"
import { Popover } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFillDrip, faUndo } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ChatWallpaperPicker from "@/Components/Messaging/ChatWallpaperPicker.vue"
import type { ChatWallpaper, ChatWallpaperCatalogue } from "@/Components/Messaging/chatWallpaper"

library.add(faFillDrip, faUndo)

const props = defineProps<{
    conversationUlid?: string
    slackChannelId?: number
    wallpaper: ChatWallpaper | null | undefined
    isSet: boolean
    buttonClass?: string
}>()

const emit = defineEmits<{ saved: [wallpaper: ChatWallpaper, isSet: boolean] }>()

const catalogue = computed<ChatWallpaperCatalogue | null>(() => (usePage().props as any)?.auth?.user?.settings?.chat_wallpapers ?? null)
const hasNewSeason = computed(() => !!catalogue.value?.patterns.some((pattern) => pattern.is_new))

const popover = ref()
const error = ref<string | null>(null)

const keyOfColour = (hex: string | null | undefined) => catalogue.value?.colours.find((colour) => colour.hex === hex)?.key ?? "none"
const pattern = ref("none")
const colour = ref("none")

const open = (event: Event) => {
    pattern.value = props.isSet ? props.wallpaper?.pattern ?? "none" : "none"
    colour.value = props.isSet ? keyOfColour(props.wallpaper?.colour) : "none"
    error.value = null
    popover.value?.toggle(event)
    if (hasNewSeason.value) {
        catalogue.value?.patterns.forEach((option) => (option.is_new = false))
        axios.post(route("grp.chat.staff.wallpaper.seen")).catch(() => { })
    }
}

const save = async (choice: { pattern: string; colour: string } | null) => {
    error.value = null
    try {
        const { data } = await axios.post(route("grp.chat.staff.wallpaper.update"), {
            conversation_ulid: props.conversationUlid ?? null,
            slack_channel_id: props.slackChannelId ?? null,
            pattern: choice?.pattern ?? null,
            colour: choice?.colour ?? null,
        })
        emit("saved", data.wallpaper, data.is_set)
    } catch (exception: any) {
        error.value = exception?.response?.data?.message ?? ctrans("Could not save the wallpaper")
    }
}

const choose = () => save({ pattern: pattern.value, colour: colour.value })

const useGeneral = async () => {
    pattern.value = "none"
    colour.value = "none"
    await save(null)
}
</script>

<template>
    <span class="contents">
    <button
        v-if="catalogue"
        type="button"
        v-tooltip="ctrans('Wallpaper for this chat')"
        :aria-label="ctrans('Wallpaper for this chat')"
        class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-md transition duration-200"
        :class="buttonClass ?? 'text-gray-600 hover:bg-gray-100'"
        @click="open">
        <FontAwesomeIcon icon="fal fa-fill-drip" fixed-width aria-hidden="true" />
        <span v-if="hasNewSeason" class="absolute right-1 top-1 h-2 w-2 rounded-full bg-[var(--app-accent)]" :aria-label="ctrans('New wallpaper')" />
    </button>
    <Popover v-if="catalogue" ref="popover">
        <div class="w-72 space-y-3 text-left">
            <div class="text-sm font-semibold text-gray-700">{{ ctrans("Wallpaper for this chat") }}</div>
            <div class="text-xs text-gray-500">{{ ctrans("Only you see it. It helps you tell at a glance who you are writing to.") }}</div>
            <ChatWallpaperPicker
                :pattern="pattern"
                :colour="colour"
                :catalogue="catalogue"
                compact
                @update:pattern="(value) => { pattern = value; choose() }"
                @update:colour="(value) => { colour = value; choose() }" />
            <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
            <Button v-if="isSet" type="tertiary" size="xs" :label="ctrans('Use my general one')" @click="useGeneral">
                <template #icon><FontAwesomeIcon icon="fal fa-undo" fixed-width aria-hidden="true" /></template>
            </Button>
        </div>
    </Popover>
    </span>
</template>
