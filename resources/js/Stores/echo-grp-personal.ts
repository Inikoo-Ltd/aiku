/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 08 Dec 2023 01:34:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import type { TicketBadges } from '@/types/TicketBadges'
import type { TaskBadges } from '@/types/TaskBadges'
import { useMilisecondToTime } from "@/Composables/useFormatTime"
import { differenceInMilliseconds } from 'date-fns'
import { defineStore } from "pinia";
import { useLayoutStore } from "@/Stores/layout"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import { ctrans } from "@/Composables/useTrans"
import { alertOnce, alertWayIsOn, chosenAlertSound, showAssignedPopup, showTicketPopup, type AlertSoundKind } from "@/Composables/useNotificationSound"
import { openChatPane } from "@/Composables/useChatPane"

const ticketAlertSpokenLines = (): Record<string, string> => ({ raised: ctrans('New ticket'), answered: ctrans('Ticket answered'), qa: ctrans('Ticket QA') })

const TICKET_ALERT_SOUND_KINDS: Record<string, AlertSoundKind> = { resolved: 'ticket', raised: 'ticket_new', answered: 'ticket_answered', qa: 'ticket_qa' }

interface ProgressBar {
    [key: string]: {
        [key: string]: {
            action_id: number
            action_type: string
            data: { 
                number_fails: number
                number_success: number
            },
            done: number 
            total: number
        }
    }
}

export interface CloneFamilyProgress {
    masterFamilyId: number
    masterFamily: string
    families: {
        done: number
        total: number
    }
    products: {
        done: number
        total: number
    }
    isStarting: boolean
    isFinished: boolean
}

interface CloneFamilyProgressEvent {
    master_family: string
    family_progress: {
        action_type: string
        action_id: number
    }
    pending_families: number
    done_families: number
    pending_products: number
    done_products: number
}

export const useEchoGrpPersonal = defineStore("echo-grp-personal", {
    state: () => ({
        progressBars: {} as ProgressBar,
        isShowProgress: false,
        recentlyUploaded: [],
        cloneFamilyProgress: {} as Record<number, CloneFamilyProgress>,
        isShowCloneFamilyProgress: false,
        checkedWebpageLinks: {} as Record<number, { broken_links: number }>
    }),
    getters: {
        cloneFamilyProgressList(state): CloneFamilyProgress[] {
            return Object.values(state.cloneFamilyProgress)
        },
        hasCloneFamilyProgress(state): boolean {
            return Object.keys(state.cloneFamilyProgress).length > 0
        },
    },
    actions: {
        subscribe(userID: number) {
            let param = window.Echo.private("grp.personal." + userID)
                .listen(
                    ".action-progress",
                    (eventData) => {
                    // console.log(eventData)

                    // Update the state
                    this.$patch({
                        progressBars: {
                            [eventData.action_type]: {
                                [eventData.action_id]: {
                                    ...eventData,
                                    time_echo: new Date(),
                                    estimatedTime: useMilisecondToTime(differenceInMilliseconds(new Date(), this.progressBars?.[eventData.action_type]?.[eventData.action_id]?.time_echo ?? new Date()) * (eventData.total-eventData.done))
                                    // useEstimatedTime(new Date(), (this.progressBars?.[eventData.action_type]?.[eventData.action_id]?.time_echo ?? 0))
                                }
                            }
                        }
                    })

                    // console.log(this.progressBars[eventData.action_type][eventData.action_id].estimatedTime)

                    // To show the progress bars
                    if(!this.isShowProgress) this.isShowProgress = true

                    // If already reach 100%
                    if(eventData.done >= eventData.total){
                        // Add data to recentlyUploaded, to show in history
                        this.recentlyUploaded.push(this.progressBars[eventData.action_type][eventData.action_id])

                        if (eventData.action_type === 'Upload' && eventData.data?.number_fails > 0) return

                        setTimeout(() => {
                            delete this.progressBars[eventData.action_type][eventData.action_id]

                            // If no more progress, then hide the bar
                            const uploadCount = Object.values(this.progressBars[eventData.action_type])
                            if(!uploadCount.length) this.isShowProgress = false
                        }, 4000)
                    }
                }
            )
            .listen('.waiting-items-count-update', (eventData: { dispatching_waiting_count: number; crm_waiting_count: number }) => {
                const layout = useLayoutStore()
                layout.dispatching_waiting_count = eventData.dispatching_waiting_count
                layout.crm_waiting_count = eventData.crm_waiting_count
            })
            .listen('.task-badges-update', (eventData: { task_badges: TaskBadges }) => {
                useLayoutStore().task_badges = eventData.task_badges
            })
            .listen('.development-badges-update', (eventData: { development_badges: { waiting: number, late: number, reminded?: boolean } | null }) => {
                useLayoutStore().development_badges = eventData.development_badges
            })
            .listen('.ticket-badges-update', (eventData: { ticket_badges: TicketBadges; notification: { title: string; body: string; route: string; reason?: string } | null }) => {
                const layout = useLayoutStore()
                layout.ticket_badges = eventData.ticket_badges
                const notification = eventData.notification
                if (notification) {
                    layout.notifications.unshift({ id: '', read: false, href: '', created_at: new Date(), ...notification })
                }
                const soundKind = notification?.reason ? TICKET_ALERT_SOUND_KINDS[notification.reason] : undefined
                if (notification && soundKind) {
                    if (notification.title && alertWayIsOn('ticket', 'popup')) {
                        showTicketPopup(notification.title, notification.body, notification.route)
                    }
                    alertOnce({
                        event: 'ticket',
                        key: `ticket-${notification.reason}:${notification.title}`,
                        title: notification.title,
                        body: notification.body,
                        tag: `ticket-${notification.route}`,
                        sound: chosenAlertSound(soundKind),
                        spoken: ticketAlertSpokenLines()[notification.reason ?? ''] ?? '',
                        url: notification.route,
                    })
                }
            })
            .listen('.personal-notification', (eventData: { notification: { id: string | null; title: string; body: string; route: string; kind?: string } }) => {
                const layout = useLayoutStore()
                const notification = eventData.notification
                layout.notifications.unshift({ read: false, href: '', created_at: new Date(), ...notification, id: notification.id ?? '' })

                if (notification.kind === 'chat_assigned') {
                    if (alertWayIsOn('assigned', 'popup')) {
                        showAssignedPopup(notification.title, notification.body, notification.route)
                    }
                    alertOnce({
                        event: 'assigned',
                        key: `assigned:${notification.route}:${notification.body}`,
                        title: notification.title,
                        body: notification.body,
                        tag: `assigned-${notification.route}`,
                        url: notification.route,
                        sound: chosenAlertSound('chat'),
                        spoken: notification.body,
                        onOpen: () => openChatPane(notification.route),
                    })
                }
            })
            .listen('.clone-family-progress', (eventData: CloneFamilyProgressEvent) => {
                const masterFamilyId = eventData.family_progress?.action_id
                if (!masterFamilyId) return

                if (!this.cloneFamilyProgress[masterFamilyId]) this.isShowCloneFamilyProgress = true

                this.cloneFamilyProgress[masterFamilyId] = {
                    masterFamilyId: masterFamilyId,
                    masterFamily: eventData.master_family,
                    families: {
                        done: eventData.done_families,
                        total: eventData.pending_families
                    },
                    products: {
                        done: eventData.done_products,
                        total: eventData.pending_products
                    },
                    isStarting: false,
                    isFinished: eventData.done_families >= eventData.pending_families
                        && eventData.done_products >= eventData.pending_products
                }
            })
            .listen('.webpage-links-checked', (eventData: { webpage_id: number; broken_links: number }) => {
                this.checkedWebpageLinks[eventData.webpage_id] = { broken_links: eventData.broken_links }
            })
            .listen('.staff-message', (message) => {
                useStaffMessaging().handleIncoming(message)
            })
            .listen('.staff-message-translated', (message) => {
                useStaffMessaging().replaceMessage(message)
            })
            .listen('.staff-message-reaction', (message) => {
                useStaffMessaging().applyReactionBroadcast(message)
            })
            .listen('.staff-conversation-archived', (e: {conversation_ulid: string; user_id: number; user_name: string}) => {
                useStaffMessaging().handleArchived(e)
            })
            .listen('.staff-conversation-read', (e: { conversation_ulid: string; user_id: number; last_read_at: string }) => {
                useStaffMessaging().handleRead(e)
            })
        },

        startCloneFamilyProgress(masterFamilyId: number, masterFamily: string = '') {
            this.cloneFamilyProgress[masterFamilyId] = {
                masterFamilyId: masterFamilyId,
                masterFamily: masterFamily,
                families: { done: 0, total: 0 },
                products: { done: 0, total: 0 },
                isStarting: true,
                isFinished: false
            }
            this.isShowCloneFamilyProgress = true
        },

        clearCloneFamilyProgress(masterFamilyId: number) {
            delete this.cloneFamilyProgress[masterFamilyId]
        },
    },
});
