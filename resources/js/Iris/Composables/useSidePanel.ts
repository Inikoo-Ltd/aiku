import { computed, inject, onMounted, provide, ref, watch, type Ref } from 'vue'
import { useWindowSize } from '@vueuse/core'
import { set } from 'lodash-es'
import { usePage } from '@inertiajs/vue3'

export type SidePanelTab = 'basket' | 'chat'

const sidePanelKey = 'irisSidePanel'

export const createSidePanel = (layout: any, screenType: Ref<'mobile' | 'tablet' | 'desktop'>, isChatEnabled: boolean) => {
    const isMounted = ref(false)
    const { width } = useWindowSize()
    const isWideScreen = computed(() => isMounted.value && width.value >= 1280)

    const page = usePage()
    const isOnBasketOrCheckoutPage = computed(() => /^\/app\/(basket|checkout)(\/|\?|$)/.test(page.url ?? ''))
    const canShowBasketPanel = computed(() => !!layout?.iris?.is_logged_in && !isOnBasketOrCheckoutPage.value)
    const isAvailable = computed(() => isMounted.value && screenType.value !== 'mobile' && (canShowBasketPanel.value || isChatEnabled))
    const canShowChatPanel = computed(() => isChatEnabled && isAvailable.value)
    const tab = computed<SidePanelTab>(() => {
        if (!canShowBasketPanel.value) {
            return 'chat'
        }
        if (!canShowChatPanel.value) {
            return 'basket'
        }
        return layout.rightbasket?.tab === 'chat' ? 'chat' : 'basket'
    })
    const isOpen = computed(() => isAvailable.value && !!layout.rightbasket?.show && (canShowBasketPanel.value || layout.rightbasket?.tab === 'chat'))
    const chatUnreadCount = ref(0)

    const isSplitView = computed(() => isOpen.value && isWideScreen.value)
    const reservedWidth = computed(() => isSplitView.value ? 'var(--iris-basket-width)' : '0px')

    const saveOpenState = () => {
        try {
            localStorage.setItem('rightbasket', String(!!layout.rightbasket?.show))
        } catch {
            return
        }
    }

    const toggle = (selectedTab: SidePanelTab) => {
        const isClosing = isOpen.value && tab.value === selectedTab
        set(layout, 'rightbasket.tab', selectedTab)
        set(layout, 'rightbasket.show', !isClosing)
        saveOpenState()
    }

    const close = () => {
        set(layout, 'rightbasket.show', false)
        saveOpenState()
    }

    const openChat = () => {
        set(layout, 'rightbasket.tab', 'chat')
        set(layout, 'rightbasket.show', true)
    }

    onMounted(() => {
        isMounted.value = true
        try {
            const savedOpenState = localStorage.getItem('rightbasket')
            if (savedOpenState !== null) {
                set(layout, 'rightbasket.show', savedOpenState === 'true')
            }
        } catch {
            return
        }
    })

    watch(reservedWidth, (reserved) => {
        document.documentElement.style.setProperty('--iris-side-width', reserved)
        document.body.style.paddingRight = reserved === '0px' ? '' : 'var(--iris-side-width)'
    })

    watch(() => layout.iris_variables?.cart_count, (cartCount) => {
        if (cartCount <= 0 && tab.value === 'basket') {
            set(layout, 'rightbasket.show', false)
        }
    })

    const sidePanel = { isSplitView, isAvailable, canShowBasketPanel, canShowChatPanel, tab, isOpen, chatUnreadCount, toggle, close, openChat }
    provide(sidePanelKey, sidePanel)

    return sidePanel
}

export type SidePanel = ReturnType<typeof createSidePanel>

export const websiteThemeVariables = (layout: any): Record<string, string> => Object.fromEntries(
    (layout?.iris?.theme?.color ?? []).slice(0, 6).map((color: string, index: number) => [`--theme-color-${index}`, color])
)

export const useSidePanel = () => inject<SidePanel | null>(sidePanelKey, null)

export const useOpenBasketPanelOnClick = () => {
    const sidePanel = useSidePanel()

    return (event: MouseEvent) => {
        if (!sidePanel?.canShowBasketPanel.value) {
            return
        }
        event.preventDefault()
        event.stopPropagation()
        sidePanel.toggle('basket')
    }
}
