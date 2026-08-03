/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 08 Dec 2023 01:34:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

import { useMilisecondToTime } from "@/Composables/useFormatTime"
import { differenceInMilliseconds } from 'date-fns'
import { defineStore } from "pinia";
import { useLayoutStore } from "@/Stores/layout"

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
        isShowCloneFamilyProgress: false
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

                        // Delete data in 4 seconds after finish
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
