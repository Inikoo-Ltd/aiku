<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { router, usePage } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHashtag, faComments, faCheck, faQuestionCircle, faLongArrowRight, faExternalLink } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLayoutStore } from "@/Stores/layout"
import { useVerbaJoin } from "@/Composables/useVerbaJoin"
import VerbaArt from "@/Components/Messaging/VerbaArt.vue"

library.add(faHashtag, faComments, faCheck, faQuestionCircle, faLongArrowRight, faExternalLink)

const layout = useLayoutStore()
const visible = ref(false)

const isVerbaRoute = computed(() => (layout.currentRoute ?? "").startsWith("grp.chat.staff."))
const { slackJoin, mustJoin } = useVerbaJoin()

const page = usePage()
watch([mustJoin, () => page.component], ([isJoining, component]) => {
    if (isJoining && component !== "Chat/StaffMessaging") {
        router.visit(route("grp.chat.staff.feed"), { replace: true })
    }
}, { immediate: true })

const lines = computed(() => [
    { meaning: ctrans("Channels, public and private"), name: "V·Desks", icon: "fal fa-hashtag", colour: "#7c5cff" },
    { meaning: ctrans("Direct messages"), name: "V·Ds", icon: "fal fa-comments", colour: "#14c2a3" },
])

watch(isVerbaRoute, (isVerba) => {
    if (isVerba && !slackJoin.value && layout.user?.settings && !layout.user.settings.verba_welcome_seen) {
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
    <div v-if="isVerbaRoute && !mustJoin" class="flex items-center">
        <button type="button" class="flex h-7 items-center gap-x-1 whitespace-nowrap text-xs text-gray-500 hover:text-gray-700" :aria-label="ctrans('What is this?')" @click="visible = true">
            <FontAwesomeIcon icon="fal fa-question-circle" fixed-width aria-hidden="true" />
            <span class="hidden lg:inline" aria-hidden="true">{{ ctrans("What is this?") }}</span>
        </button>


        <Dialog v-model:visible="visible" modal :style="{ width: '24rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
            <template #header>
                <span class="font-[Lobster] text-2xl leading-none text-gray-800">{{ ctrans("Welcome to Verba") }}</span>
            </template>
            <VerbaArt class="mx-auto mb-4 h-24 w-60" />

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
