<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckDouble, faPhone } from "@fal"
import { hasWallpaper, wallpaperStyle, type ChatWallpaper } from "@/Components/Messaging/chatWallpaper"

library.add(faCheckDouble, faPhone)

const props = defineProps<{ wallpaper: ChatWallpaper | null; messageStyle?: string }>()

const isWallpapered = computed(() => hasWallpaper(props.wallpaper))
const isBubbles = computed(() => props.messageStyle !== "rows")
const theirs = computed(() => (isWallpapered.value ? "bg-white shadow-sm" : "bg-gray-100"))

const channels = ["general", "buying", "warehouse", "trnava"]
</script>

<template>
    <div class="flex overflow-hidden rounded-lg border border-gray-200 bg-white text-sm" :aria-label="ctrans('Preview')">
        <div class="flex w-28 shrink-0 flex-col gap-0.5 bg-[var(--vx-sidebar,#3f0e40)] px-1.5 py-2 text-xs text-[var(--vx-sidebar-text,rgba(255,255,255,0.7))]">
            <span class="mb-1 px-1.5 font-[Lobster] text-base text-[var(--vx-sidebar-strong,#ffffff)]">Verba</span>
            <span
                v-for="channel in channels"
                :key="channel"
                class="truncate rounded px-1.5 py-0.5"
                :class="channel === 'trnava' ? 'bg-[var(--vx-sidebar-active,rgba(255,255,255,0.92))] text-[var(--vx-sidebar-active-text,#39063a)]' : ''"># {{ channel }}</span>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-2 p-3" :style="wallpaperStyle(wallpaper)">
            <div class="flex justify-center"><span class="rounded-full border border-gray-200 bg-white px-2.5 py-0.5 text-xxs font-medium text-gray-600">{{ ctrans("Today") }}</span></div>
            <template v-if="isBubbles">
                <div class="flex justify-start"><div class="max-w-[80%] rounded-lg px-3 py-2 text-gray-900" :class="theirs">{{ ctrans("Morning! Is the Trnava pallet ready?") }}</div></div>
                <div class="flex justify-end"><div class="max-w-[80%] rounded-lg bg-[var(--app-accent)] px-3 py-2 text-[var(--app-accent-text)]">{{ ctrans("Yes, it goes out at 2pm") }} <FontAwesomeIcon icon="fal fa-check-double" class="ml-1 text-xs opacity-80" aria-hidden="true" /></div></div>
            </template>
            <template v-else>
                <div class="rounded-lg px-2 py-1" :class="isWallpapered ? 'bg-white/80' : ''"><div class="text-xs font-bold text-gray-900">Toni</div><div class="text-gray-800">{{ ctrans("Morning! Is the Trnava pallet ready?") }}</div></div>
                <div class="rounded-lg px-2 py-1" :class="isWallpapered ? 'bg-white/80' : ''"><div class="text-xs font-bold text-gray-900">{{ ctrans("You") }}</div><div class="text-gray-800">{{ ctrans("Yes, it goes out at 2pm") }}</div></div>
            </template>
            <div class="flex justify-center"><span class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs text-gray-600" :class="theirs"><FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" aria-hidden="true" />{{ ctrans("Call, 2 min") }}</span></div>
        </div>
    </div>
</template>
