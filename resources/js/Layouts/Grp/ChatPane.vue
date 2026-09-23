<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 24 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { chatPaneUrl, closeChatPane } from "@/Composables/useChatPane"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faTimes)

const layout = inject("layout", layoutStructure)
</script>

<template>
    <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-x-full"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-to-class="translate-x-full">
        <aside
            v-if="chatPaneUrl"
            class="fixed inset-y-0 right-0 z-[21] w-full md:w-[var(--chat-pane)] flex flex-col bg-white border-l border-gray-200 shadow-xl"
            :class="layout.messagingSidebar.show ? 'md:right-56' : (layout.messagingSidebar.micro ? 'md:right-4' : 'md:right-12')">
            <div class="flex items-center justify-between gap-2 h-10 px-3 border-b border-gray-200 shrink-0">
                <span class="text-sm font-semibold text-gray-700">{{ ctrans("Customer chats") }}</span>
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
                <iframe :src="chatPaneUrl" class="relative w-full h-full border-0" :title="ctrans('Customer chats')" />
            </div>
        </aside>
    </Transition>
</template>
