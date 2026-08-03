import { ref } from 'vue'

const isIrisSearchMobileOpen = ref(false)

export function useIrisSearchMobile() {
    return {
        isIrisSearchMobileOpen,
        openIrisSearchMobile: () => {
            isIrisSearchMobileOpen.value = true
        },
        closeIrisSearchMobile: () => {
            isIrisSearchMobileOpen.value = false
        },
    }
}
