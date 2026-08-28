/**
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 08-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/


import { router } from '@inertiajs/vue3'
import { useLiveUsers } from '@/Stores/active-users'
import { useChatAgentPresence } from '@/Composables/useChatAgentPresence'
import { trans } from 'laravel-vue-i18n'

export const useLogoutAuth = (dataUser, options) => {
    router.post(route('grp.logout'), {}, options)

    const dataActiveUser = {
        ...dataUser,
        name: null,
        last_active: new Date(),
        action: 'logout',
        current_page: {
            label: trans('Logout'),
            url: null,
            icon_left: null,
            icon_right: null,
        },
    }
    window.Echo.join(`grp.live.users`).whisper('otherIsNavigating', dataActiveUser)
    useLiveUsers().unsubscribe()  // Unsubscribe from Laravel Echo
    useChatAgentPresence().stop()  // Stop the chat agent heartbeat, logout already set them offline
}
