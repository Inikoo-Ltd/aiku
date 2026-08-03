import { ref } from 'vue'

// The mobile overlay is reachable from three different controls; whichever opened it is
// sent along with the search request so the website analytics can compare their use
export type IrisSearchMobileSource = 'mobile_top_bar' | 'mobile_floating_button' | 'mobile_sidebar'

const isIrisSearchMobileOpen = ref(false)
const irisSearchMobileSource = ref<IrisSearchMobileSource | null>(null)

export function useIrisSearchMobile() {
    return {
        isIrisSearchMobileOpen,
        irisSearchMobileSource,
        openIrisSearchMobile: (source: IrisSearchMobileSource) => {
            irisSearchMobileSource.value = source
            isIrisSearchMobileOpen.value = true
        },
        closeIrisSearchMobile: () => {
            isIrisSearchMobileOpen.value = false
        },
    }
}
