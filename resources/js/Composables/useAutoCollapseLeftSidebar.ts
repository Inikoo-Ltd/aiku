import { computed, onMounted, watch } from 'vue'
import { useWindowSize } from '@vueuse/core'
import { useSidePanel } from '@/Iris/Composables/useSidePanel'

const minimumWidthForOpenSidebar = 1200

export const useAutoCollapseLeftSidebar = (layout: any) => {
    const { width } = useWindowSize()
    const sidePanel = useSidePanel()
    const isNarrow = computed(() => width.value - (sidePanel?.isSplitView.value ? 400 : 0) < minimumWidthForOpenSidebar)

    const applySidebarState = () => {
        if (isNarrow.value) {
            layout.leftSidebar.show = false
            return
        }
        try {
            layout.leftSidebar.show = localStorage.getItem('leftSideBar') !== 'false'
        } catch {
            layout.leftSidebar.show = true
        }
    }

    onMounted(applySidebarState)
    watch(isNarrow, applySidebarState)
}
