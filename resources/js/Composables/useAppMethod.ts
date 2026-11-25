/**
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 08-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/


import { router } from '@inertiajs/vue3'
import { announcedUser, useLiveUsers } from '@/Stores/active-users'
import { useChatAgentPresence } from '@/Composables/useChatAgentPresence'
import { clearCachedChats } from '@/Composables/useStaffChatCache'
import { clearAllDrafts } from '@/Components/Messaging/deskDrafts'
import { ctrans } from '@/Composables/useTrans'

export const useLogoutAuth = (dataUser, options) => {
    clearCachedChats()
    clearAllDrafts()
    router.post(route('grp.logout'), {}, options)

    const dataActiveUser = {
        ...announcedUser(dataUser),
        last_active: new Date(),
        action: 'logout',
        current_page: {
            label: ctrans('Logout'),
            url: null,
            icon_left: null,
            icon_right: null,
        },
    }
    window.Echo.join(`grp.live.users`).whisper('otherIsNavigating', dataActiveUser)
    useLiveUsers().unsubscribe()  // Unsubscribe from Laravel Echo
    useChatAgentPresence().stop()  // Stop the chat agent heartbeat, logout already set them offline
}
