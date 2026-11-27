<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHashtag, faDrawSquare, faComments, faCheck, faQuestionCircle, faLongArrowRight, faExternalLink } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLayoutStore } from "@/Stores/layout"

library.add(faHashtag, faDrawSquare, faComments, faCheck, faQuestionCircle, faLongArrowRight, faExternalLink)

const layout = useLayoutStore()
const visible = ref(false)

const isVerbaRoute = computed(() => (layout.currentRoute ?? "").startsWith("grp.chat.staff."))

const lines = computed(() => [
    { meaning: ctrans("Public channels"), name: "V·Desks", icon: "fal fa-hashtag", colour: "#7c5cff" },
    { meaning: ctrans("Private channels"), name: "Verbations", icon: "fal fa-draw-square", colour: "#ff5a7a" },
    { meaning: ctrans("Direct messages"), name: "V·Ds", icon: "fal fa-comments", colour: "#14c2a3" },
])

watch(isVerbaRoute, (isVerba) => {
    if (isVerba && layout.user?.settings && !layout.user.settings.verba_welcome_seen) {
        visible.value = true
    }
}, { immediate: true })

watch(visible, (isVisible) => {
    if (isVisible || !layout.user?.settings || layout.user.settings.verba_welcome_seen) {
        return
    }
    layout.user.settings.verba_welcome_seen = true
    axios.patch(route("grp.models.profile.update"), { verba_welcome_seen: true }).catch(() => {
        // ponytail: not saved means it shows once more on the next page load, nothing lost
    })
})
</script>

<template>
    <div v-if="isVerbaRoute" class="flex items-center">
        <button type="button" class="flex h-7 items-center gap-x-1 whitespace-nowrap text-xs text-gray-500 hover:text-gray-700" :aria-label="ctrans('What is this?')" @click="visible = true">
            <FontAwesomeIcon icon="fal fa-question-circle" fixed-width aria-hidden="true" />
            <span class="hidden lg:inline" aria-hidden="true">{{ ctrans("What is this?") }}</span>
        </button>

        <Dialog v-model:visible="visible" modal :style="{ width: '24rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
            <template #header>
                <span class="font-[Lobster] text-2xl leading-none text-gray-800">{{ ctrans("Welcome to Verba") }}</span>
            </template>
            <svg viewBox="0 0 240 96" class="mx-auto mb-4 h-24 w-60" aria-hidden="true">
                <circle cx="104" cy="54" r="26" fill="#7c5cff" opacity="0.9" />
                <circle cx="136" cy="42" r="26" fill="#ff5a7a" opacity="0.85" />
                <g transform="translate(18 14)">
                    <rect width="56" height="34" rx="12" fill="#ede9ff" />
                    <path d="M14 34 l-4 10 l12 -10z" fill="#ede9ff" />
                    <text x="28" y="23" text-anchor="middle" font-size="16" font-weight="700" fill="#7c5cff">#</text>
                </g>
                <g transform="translate(168 8)">
                    <rect width="56" height="34" rx="12" fill="#ffe4ea" />
                    <path d="M42 34 l4 10 l-12 -10z" fill="#ffe4ea" />
                    <rect x="21" y="15" width="14" height="11" rx="2" fill="#ff5a7a" />
                    <path d="M24 15 v-3 a4 4 0 0 1 8 0 v3" stroke="#ff5a7a" stroke-width="2" fill="none" />
                </g>
                <g transform="translate(168 56)">
                    <rect width="56" height="30" rx="12" fill="#d9f6ef" />
                    <path d="M42 30 l4 8 l-12 -8z" fill="#d9f6ef" />
                    <circle cx="20" cy="15" r="3" fill="#14c2a3" />
                    <circle cx="28" cy="15" r="3" fill="#14c2a3" />
                    <circle cx="36" cy="15" r="3" fill="#14c2a3" />
                </g>
                <circle cx="44" cy="74" r="5" fill="#ffb020" />
                <circle cx="60" cy="84" r="3" fill="#14c2a3" opacity="0.7" />
            </svg>

            <ul class="flex flex-col gap-y-2">
                <li v-for="line in lines" :key="line.name" class="grid grid-cols-[1fr_auto_1fr] items-center gap-x-3 rounded-lg bg-gray-50 px-3 py-2">
                    <span class="text-sm text-gray-600">{{ line.meaning }}</span>
                    <FontAwesomeIcon icon="fal fa-long-arrow-right" class="text-xl text-gray-400" fixed-width aria-hidden="true" />
                    <span class="flex items-center justify-end gap-x-1.5 whitespace-nowrap text-sm font-semibold" :style="{ color: line.colour }">
                        <FontAwesomeIcon :icon="line.icon" fixed-width aria-hidden="true" />
                        {{ line.name }}
                    </span>
                </li>
            </ul>

            <a href="https://aiku.io/docs/verba" target="_blank" rel="noopener" class="mt-3 flex items-center justify-center gap-x-1 text-xs text-gray-500 hover:text-gray-700 hover:underline">
                {{ ctrans("Learn more") }}
                <FontAwesomeIcon icon="fal fa-external-link" fixed-width aria-hidden="true" />
            </a>

            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="primary" full :label="ctrans('Got it')" @click="visible = false">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
