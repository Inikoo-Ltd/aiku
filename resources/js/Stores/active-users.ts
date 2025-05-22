/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 29 Dec 2023 12:46:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import { Image } from '@/types/Image'
import { usePage } from '@inertiajs/vue3'
import { defineStore } from 'pinia'
// import { useLayoutStore } from './layout'

// const layout = useLayoutStore()

interface LiveUser {
    id: number
    username: string  // aiku
    avatar_thumbnail: Image
    name: string  // Mr. Aiku
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


export const useLiveUsers = defineStore('useLiveUsers', {
    state: () => ({
        liveUsers: { } as LiveUsers,
    }),
    getters: {
        count: (state) => Object.keys(state.liveUsers).length,
        liveUsersArray: (state) => Object.values(state.liveUsers),
        // listUsersName: (state) => Object.values(state.liveUsers).map(user => user?.name ?? user?.username)?.join(', ')
        // liveUsersWithoutMe: (state) => state.liveUsers.filter((liveUser, keyUser) => keyUser != layout.user.id )
    },
    actions: {
        // getFilteredLiveUsers() {
        //     const xxx = {}
        //     Object.keys(this.liveUsers).forEach((key) => {
        //         if(!(
        //             (this.liveUsers[key].action === 'leave' && useIsFutureIsAPast(this.liveUsers[key]?.last_active, 300)) ||
        //             (this.liveUsers[key].action === 'logout' && useIsFutureIsAPast(this.liveUsers[key]?.last_active, 3)))
        //         ){
        //             console.log(xxx[key] = this.liveUsers[key])
        //             return xxx[key] = this.liveUsers[key]
        //         }}
        //     )

        //     return xxx
        // },
        unsubscribe() {
            window.Echo.leave(`grp.live.users`)
        },
        subscribe() {

            window.Echo.join(`grp.live.users`)
                // .here((rawUsers) => {
                //     console.log('Who is here: ', rawUsers);

                // })

                .joining((user) => {
                    console.log('Someone join')
                    // if UserA join, then others send their data to UserA
                    window.Echo.join(`grp.live.users`).whisper(`sendTo${user.id}`, this.liveUsers[usePage().props.auth.user?.id])
                })

                .leaving((user: {id: number, alias: string, name: string}) => {
                    // console.log('Someone leaved: ', user)

                    // If user 'logout', no need to set the action to 'leave'
                    if (this.liveUsers[user.id]?.action && this.liveUsers[user.id].action != 'logout') {
                        this.liveUsers[user.id].action = 'leave'
                        this.liveUsers[user.id].last_active = new Date()
                    }
                })

                .error((error) => {
                    console.log('error', error)
                })

                .listenForWhisper('otherIsNavigating', (e: LiveUser) => {
                    // On the first load and on navigating page
                    // console.log('qwer', e)
                    this.liveUsers[e.id] = e
                    // console.log('qwer', this.liveUsers)
                })

                .listenForWhisper(`sendTo${usePage().props.auth.user?.id}`, (otherUser: LiveUser) => {
                    // console.log('receive the emit')
                    // On the first load and on navigating page
                    this.liveUsers[otherUser.id] = otherUser
                    // console.log('qwer', this.liveUsers)
                })
        },
    },
})
