import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { ctrans } from '@/Composables/useTrans'
import { clearIrisSession } from '@/Composables/clearIrisSession'

export const useIrisLogout = (layout: any) => {
    const isLoadingLogout = ref(false)
    let restoreIrisSession: (() => void) | null = null

    const logout = () => {
        router.post(
            '/app/logout',
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    isLoadingLogout.value = true
                    restoreIrisSession = clearIrisSession(layout)
                },
                onError: () => {
                    restoreIrisSession?.()
                    notify({
                        title: ctrans('Something went wrong'),
                        text: ctrans('Failed to logout'),
                        type: 'error',
                    })
                },
                onFinish: () => {
                    isLoadingLogout.value = false
                },
            }
        )
    }

    return { isLoadingLogout, logout }
}
