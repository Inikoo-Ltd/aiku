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

const props = defineProps<{ wallpaper: ChatWallpaper | null }>()

const isWallpapered = computed(() => hasWallpaper(props.wallpaper))
</script>

<template>
    <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white p-3 text-sm" :style="wallpaperStyle(wallpaper)" :aria-label="ctrans('Preview')">
        <div class="flex justify-center"><span class="rounded-full border border-gray-200 bg-white px-2.5 py-0.5 text-xxs font-medium text-gray-600">{{ ctrans("Today") }}</span></div>
        <div class="flex justify-start"><div class="max-w-[80%] rounded-lg px-3 py-2 text-gray-900" :class="isWallpapered ? 'bg-white shadow-sm' : 'bg-gray-100'">{{ ctrans("Morning! Is the Trnava pallet ready?") }}</div></div>
        <div class="flex justify-end"><div class="max-w-[80%] rounded-lg bg-[var(--app-accent)] px-3 py-2 text-[var(--app-accent-text)]">{{ ctrans("Yes, it goes out at 2pm") }} <FontAwesomeIcon icon="fal fa-check-double" class="ml-1 text-xs opacity-80" aria-hidden="true" /></div></div>
        <div class="flex justify-center"><span class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs text-gray-600" :class="isWallpapered ? 'bg-white shadow-sm' : 'bg-gray-100'"><FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" aria-hidden="true" />{{ ctrans("Call, 2 min") }}</span></div>
        <div class="flex justify-start"><div class="max-w-[80%] rounded-lg px-3 py-2 text-gray-900" :class="isWallpapered ? 'bg-white shadow-sm' : 'bg-gray-100'">{{ ctrans("Great, thanks") }} <a href="#" class="text-[var(--app-accent-strong)] underline" @click.prevent>aiku.io</a></div></div>
    </div>
</template>
