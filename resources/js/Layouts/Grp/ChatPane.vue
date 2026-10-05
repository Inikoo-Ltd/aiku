<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 24 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, onMounted, onUnmounted, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faExpandAlt, faChevronLeft, faChevronRight } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { chatPaneUrl, closeChatPane, resizeChatPane } from "@/Composables/useChatPane"
import { followPointer } from "@/Composables/followPointer"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faTimes, faExpandAlt, faChevronLeft, faChevronRight)

const layout = inject("layout", layoutStructure)
const pane = ref<HTMLElement | null>(null)
const frame = ref<HTMLIFrameElement | null>(null)
const resizing = ref(false)
const position = ref<{ at: number; total: number } | null>(null)

const step = (by: number) => frame.value?.contentWindow?.postMessage({ chatPaneStep: by }, window.location.origin)

const onFrameMessage = (event: MessageEvent) => {
    if (event.source === frame.value?.contentWindow && event.data?.chatPanePosition) {
        position.value = event.data.chatPanePosition
    }
}

watch(chatPaneUrl, () => {
    position.value = null
})
onMounted(() => window.addEventListener("message", onFrameMessage))
onUnmounted(() => window.removeEventListener("message", onFrameMessage))

const startResize = (event: PointerEvent) => {
    const paneRight = pane.value?.getBoundingClientRect().right ?? window.innerWidth
    resizing.value = true
    followPointer(event, (move) => resizeChatPane(paneRight - move.clientX), () => {
        resizing.value = false
    })
}

const openFullView = () => {
    const shown = frame.value?.contentWindow?.location.href
    const url = shown?.startsWith("http") ? shown : chatPaneUrl.value
    closeChatPane()
    if (url) {
        window.open(url, "aiku-chat-inbox", `popup,width=${Math.round(screen.availWidth * 0.75)},height=${screen.availHeight}`)?.focus()
    }
}
</script>

<template>
    <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-x-full"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-to-class="translate-x-full">
        <aside
            v-if="chatPaneUrl"
            ref="pane"
            class="fixed inset-y-0 right-0 z-[21] w-full md:w-[var(--chat-pane)] flex flex-col bg-white border-l border-gray-200 shadow-xl"
            :class="layout.messagingSidebar.show ? 'md:right-56' : (layout.messagingSidebar.micro ? 'md:right-4' : 'md:right-12')">
            <div class="absolute inset-y-0 -left-1 z-10 hidden w-2 cursor-col-resize hover:bg-[--app-accent] md:block" @pointerdown="startResize" />
            <div class="flex items-center justify-between gap-2 h-10 px-3 border-b border-gray-200 shrink-0">
                <span class="text-sm font-semibold text-gray-700">{{ ctrans("Customer chats") }}</span>
                <div class="flex flex-1 items-center justify-center gap-1 text-xs tabular-nums text-gray-500">
                    <template v-if="position?.total">
                        <button
                            type="button"
                            class="w-7 h-7 rounded hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30 disabled:hover:bg-transparent"
                            :disabled="position.at <= 1"
                            v-tooltip="ctrans('Previous chat')"
                            @click="step(-1)">
                            <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
                        </button>
                        <span>{{ position.at || "–" }} / {{ position.total }}</span>
                        <button
                            type="button"
                            class="w-7 h-7 rounded hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30 disabled:hover:bg-transparent"
                            :disabled="position.at >= position.total"
                            v-tooltip="ctrans('Next chat')"
                            @click="step(1)">
                            <FontAwesomeIcon icon="fal fa-chevron-right" fixed-width aria-hidden="true" />
                        </button>
                    </template>
                </div>
                <button
                    type="button"
                    class="w-8 h-8 rounded text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                    v-tooltip="ctrans('Full view')"
                    @click="openFullView">
                    <FontAwesomeIcon icon="fal fa-expand-alt" fixed-width aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="w-8 h-8 rounded text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                    v-tooltip="ctrans('Close')"
                    @click="closeChatPane">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                </button>
            </div>
            <div class="relative flex-1 min-h-0">
                <LoadingIcon class="absolute inset-0 m-auto w-6 h-6 text-gray-400" />
                <iframe ref="frame" :src="chatPaneUrl" class="relative w-full h-full border-0" :class="{ 'pointer-events-none': resizing }" :title="ctrans('Customer chats')" />
            </div>
        </aside>
    </Transition>
</template>
