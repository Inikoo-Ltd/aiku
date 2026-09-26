<script setup lang="ts">
import { computed, defineAsyncComponent, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faShoppingCart, faComments, faChevronRight } from '@fal'
import { ctrans } from '@/Composables/useTrans'
import { useSidePanel, websiteThemeVariables } from '@/Iris/Composables/useSidePanel'
import { useFloatingButtonsDrag } from '@/Iris/Composables/useFloatingButtonsDrag'

const IrisRightSideBasket = defineAsyncComponent(() => import('@iris/Components/IrisRightSideBasket.vue'))
const ChatButton = defineAsyncComponent(() => import('@/Components/Chat/Customer/ChatButton.vue'))

const props = defineProps<{
    isChatEnabled: boolean
    chatConfig?: object
}>()

const layout: any = inject('layout', {})
const sidePanel = useSidePanel()!
const websiteColorVariables = computed(() => websiteThemeVariables(layout))
const floatingButtonsDrag = useFloatingButtonsDrag(layout)

const isChatOnline = ref(false)
let chatAvailabilityTimer: ReturnType<typeof setInterval> | null = null

const refreshChatAvailability = async () => {
    if (!sidePanel.canShowChatPanel.value || !layout?.iris?.shop?.id) {
        return
    }
    try {
        const response = await axios.get(`${layout?.appUrl ?? ''}/app/api/chats/availability`, {
            params: { shop_id: layout.iris.shop.id },
        })
        isChatOnline.value = !!response.data?.is_online
    } catch {
        isChatOnline.value = false
    }
}

watch(() => sidePanel.canShowChatPanel.value, refreshChatAvailability)

onMounted(() => {
    refreshChatAvailability()
    chatAvailabilityTimer = setInterval(refreshChatAvailability, 5 * 60 * 1000)
})

onBeforeUnmount(() => {
    if (chatAvailabilityTimer) {
        clearInterval(chatAvailabilityTimer)
    }
})

const onFloatingChatClick = () => {
    if (floatingButtonsDrag.wasJustDragged()) {
        return
    }
    sidePanel.toggle('chat')
}

const onFloatingBasketClick = () => {
    if (floatingButtonsDrag.wasJustDragged()) {
        return
    }
    sidePanel.toggle('basket')
}
</script>

<template>
    <div :style="websiteColorVariables">
        <div v-if="sidePanel.isAvailable.value">
            <button
                v-if="!sidePanel.isOpen.value && sidePanel.canShowBasketPanel.value"
                type="button"
                :aria-label="ctrans('Basket')"
                v-tooltip="{ value: ctrans('Drag up or down to move'), position: 'left' }"
                class="side-action-button side-action-floating"
                :style="{ bottom: `${(sidePanel.canShowChatPanel.value ? 88 : 24) + floatingButtonsDrag.offset.value}px` }"
                @pointerdown="floatingButtonsDrag.onPointerDown"
                @pointermove="floatingButtonsDrag.onPointerMove"
                @pointerup="floatingButtonsDrag.onPointerUp"
                @pointercancel="floatingButtonsDrag.onPointerUp"
                @click="onFloatingBasketClick"
            >
                <FontAwesomeIcon :icon="faShoppingCart" class="text-base" fixed-width aria-hidden="true" />
                <span v-if="layout.iris_variables?.cart_count > 0" class="side-action-badge">
                    {{ layout.iris_variables?.cart_count }}
                </span>
            </button>

            <button
                v-if="!sidePanel.isOpen.value && sidePanel.canShowChatPanel.value"
                type="button"
                :aria-label="isChatOnline ? ctrans('Live chat') : ctrans('Help')"
                v-tooltip="{ value: ctrans('Drag up or down to move'), position: 'left' }"
                class="side-action-button side-action-floating"
                :style="{ bottom: `${24 + floatingButtonsDrag.offset.value}px` }"
                @pointerdown="floatingButtonsDrag.onPointerDown"
                @pointermove="floatingButtonsDrag.onPointerMove"
                @pointerup="floatingButtonsDrag.onPointerUp"
                @pointercancel="floatingButtonsDrag.onPointerUp"
                @click="onFloatingChatClick"
            >
                <FontAwesomeIcon :icon="faComments" class="text-base" fixed-width aria-hidden="true" />
                <span v-if="sidePanel.chatUnreadCount.value > 0" class="side-action-badge">
                    {{ sidePanel.chatUnreadCount.value }}
                </span>
            </button>

            <aside
                v-show="sidePanel.isOpen.value"
                class="fixed top-0 right-0 z-[51] h-screen basket-drawer border-l border-gray-200 bg-white flex flex-col"
                :class="sidePanel.isSplitView.value ? '' : 'shadow-xl'"
            >
                <div v-if="sidePanel.canShowBasketPanel.value && sidePanel.canShowChatPanel.value" class="shrink-0 flex items-end gap-x-2 px-3 pt-2 border-b border-gray-200" role="tablist">
                    <button
                        v-for="panelTab in (['basket', 'chat'] as const)"
                        :key="panelTab"
                        type="button"
                        role="tab"
                        :aria-selected="sidePanel.tab.value === panelTab"
                        class="group flex-1 -mb-px flex items-center justify-center gap-x-2 rounded-t-md border px-3 py-2 text-sm font-medium transition-colors focus:outline-none"
                        :class="sidePanel.tab.value === panelTab ? 'side-panel-tab-selected bg-white border-gray-200 text-gray-900' : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-gray-100 hover:text-gray-800'"
                        @click="sidePanel.toggle(panelTab)"
                    >
                        <span class="side-action-button side-action-tab transition-opacity" :class="sidePanel.tab.value === panelTab ? 'is-active' : 'opacity-50 group-hover:opacity-100'">
                            <FontAwesomeIcon :icon="panelTab === 'basket' ? faShoppingCart : faComments" class="text-sm" fixed-width aria-hidden="true" />
                            <span v-if="panelTab === 'basket' && layout.iris_variables?.cart_count > 0" class="side-action-badge">{{ layout.iris_variables?.cart_count }}</span>
                            <span v-if="panelTab === 'chat' && sidePanel.chatUnreadCount.value > 0" class="side-action-badge">{{ sidePanel.chatUnreadCount.value }}</span>
                        </span>
                        {{ panelTab === 'basket' ? ctrans('Basket') : isChatOnline ? ctrans('Live chat') : ctrans('Help') }}
                    </button>
                </div>

                <button
                    type="button"
                    :aria-label="ctrans('Close')"
                    class="absolute -left-4 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full flex items-center justify-center bg-white border border-gray-200 shadow-sm text-gray-500 hover:text-gray-800 hover:border-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400"
                    @click="sidePanel.close"
                >
                    <FontAwesomeIcon :icon="faChevronRight" fixed-width aria-hidden="true" />
                </button>

                <div v-if="sidePanel.canShowBasketPanel.value" v-show="sidePanel.tab.value === 'basket'" class="flex-1 min-h-0">
                    <IrisRightSideBasket
                        v-if="layout.iris_variables?.cart_count > 0"
                        :isOpen="sidePanel.isOpen.value && sidePanel.tab.value === 'basket'"
                    />
                    <div v-else class="h-full flex flex-col items-center justify-center gap-y-2 text-sm text-gray-500">
                        <FontAwesomeIcon :icon="faShoppingCart" class="text-3xl text-gray-300" fixed-width aria-hidden="true" />
                        {{ ctrans("Your basket is empty") }}
                    </div>
                </div>

                <ChatButton
                    v-if="sidePanel.canShowChatPanel.value"
                    v-show="sidePanel.tab.value === 'chat'"
                    docked
                    class="flex-1 min-h-0"
                    :active="sidePanel.isOpen.value && sidePanel.tab.value === 'chat'"
                    :chatConfig="chatConfig"
                    @unread="(count: number) => sidePanel.chatUnreadCount.value = count"
                    @requestOpen="sidePanel.openChat"
                />
            </aside>
        </div>

        <ChatButton data="null" v-if="props.isChatEnabled && !sidePanel.isAvailable.value" :chatConfig="chatConfig" />
    </div>
</template>

<style>
.side-action-button {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    background-color: color-mix(in srgb, var(--theme-color-4) 90%, transparent);
    color: var(--theme-color-5);
    transition: background-color 150ms;
}

.side-action-button:hover,
.group:hover .side-action-button,
.side-action-button.is-active {
    background-color: color-mix(in srgb, var(--theme-color-4) 85%, black);
}

.side-action-floating {
    position: fixed;
    right: 0.75rem;
    z-index: 60;
    width: 3rem;
    height: 3rem;
    border-radius: 9999px;
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    touch-action: none;
}

.side-action-tab {
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
}

.side-panel-tab-selected {
    border-bottom-color: #fff;
}

.side-action-button:focus {
    outline: none;
}

.side-action-badge {
    position: absolute;
    top: -0.375rem;
    right: -0.375rem;
    min-width: 1.125rem;
    height: 1.125rem;
    padding: 0 0.25rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    background-color: white;
    color: #1f2937;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.2);
}

:root {
    --iris-basket-width: min(92vw, 400px);
}

.basket-drawer {
    width: var(--iris-basket-width);
    box-sizing: border-box;
}
</style>
