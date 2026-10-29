/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Dec 2023 12:46:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import type { Image } from '@/types/Image'
import { usePage } from '@inertiajs/vue3'
import { defineStore } from 'pinia'
// import { useLayoutStore } from './layout'

// const layout = useLayoutStore()

interface LiveUser {
    id: number
    username: string  // aiku
    contact_name?: string  // Mr. Aiku
    avatar_thumbnail: Image
    action: string  // navigate, leave, logout
    last_active: Date
    current_page?: {
        label: string,
        url: string
        icon_left: {
            icon: string | string[]
            class: string
        }
        icon_right: {
            icon: string | string[]
            class: string
        }
    }
}
interface LiveUsers {
    [key: number]: LiveUser
}

export const announcedUser = (user: { id: number, username: string, contact_name?: string }) => ({
    id: user?.id,
    username: user?.username,
    contact_name: user?.contact_name,
})


export const useLiveUsers = defineStore('useLiveUsers', {
    state: () => ({
        liveUsers: { } as LiveUsers,
        isConnected: false,
    }),
    getters: {
        count: (state) => Object.keys(state.liveUsers).length,
        liveUsersArray: (state) => Object.values(state.liveUsers),
        // listUsersName: (state) => Object.values(state.liveUsers).map(user => user?.name ?? user?.username)?.join(', ')
        // liveUsersWithoutMe: (state) => state.liveUsers.filter((liveUser, keyUser) => keyUser != layout.user.id )
    },
    actions: {
        unsubscribe() {
            window.Echo.leave(`grp.live.users`)
        },
        subscribe() {
            const connection = window.Echo.connector?.pusher?.connection
            this.isConnected = connection?.state === 'connected'
            connection?.bind('state_change', ({ current }: { current: string }) => {
                this.isConnected = current === 'connected'
            })

            window.Echo.join(`grp.live.users`)
                // here() fires on every (re)connection, including waking from sleep, so it is
                // what repopulates the list without a page refresh
                .here((members: { id: number, alias: string, name: string }[]) => {
                    members.forEach((member) => {
                        if (!this.liveUsers[member.id]) {
                            this.liveUsers[member.id] = {
                                id: member.id,
                                username: member.alias,
                                contact_name: member.name,
                                action: 'navigate',
                                last_active: new Date(),
                            } as LiveUser
                        }
                    })

                    const me = this.liveUsers[usePage().props?.auth?.user?.id]
                    if (me) {
                        window.Echo.join(`grp.live.users`).whisper('otherIsNavigating', me)
                    }
                })

                .joining((user: { id: number, alias: string, name: string }) => {
                    if (!this.liveUsers[user.id]) {
                        this.liveUsers[user.id] = {
                            id: user.id,
                            username: user.alias,
                            contact_name: user.name,
                            action: 'navigate',
                            last_active: new Date(),
                        } as LiveUser
                    }
                })

                .leaving((user: {id: number, alias: string, name: string}) => {
                    // console.log('Someone leaved: ', user)

                    // If user 'logout', no need to set the action to 'leave'
                    if (this.liveUsers[user?.id]?.action && this.liveUsers[user?.id].action != 'logout') {
                        this.liveUsers[user?.id].action = 'leave'
                        this.liveUsers[user?.id].last_active = new Date()
                    }
                })

                .error((error) => {
                    console.error('error', error)
                })

                .listenForWhisper('otherIsNavigating', (e: LiveUser) => {
                    // On the first load and on navigating page
                    // console.log('qwer', e)
                    this.liveUsers[e?.id] = e
                    // console.log('qwer', this.liveUsers)
                })
        },
    },
})
