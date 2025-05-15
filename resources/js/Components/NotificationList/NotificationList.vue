<script setup lang='ts'>
import { faEnvelope, faEnvelopeOpenText } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useFormatTime } from '@/Composables/useFormatTime'
import { Link } from "@inertiajs/vue3"
import { inject, onMounted, onBeforeUnmount, ref } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import Profile from "@/Pages/Grp/Profile.vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { trans } from 'laravel-vue-i18n'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

library.add(faEnvelope, faEnvelopeOpenText)

const props = defineProps<{
    close: Function
}>()

const layout = inject('layout', layoutStructure)
// console.log('ewew', layout.user.notifications)


// Method: set all notifications to read = true
const isLoading = ref(false)
const setAllToRead = async () => {
    isLoading.value = true
    try {
        const response = await axios.patch(
            route('grp.models.notifications.all.read')
        )

        layout.user.notifications.map(notif => notif.read = true)  // Manipulation data in FE
    } catch (error: any) {
        console.log(error)
        notify({
            title: 'Error on set notifications.',
            text: error,
            type: 'error'
        })
    } finally {
        isLoading.value = false
    }
}

// Method: set selected notification to read = true
const setNotificationToRead = async (notifId: string) => {
    // props.close()
    if (layout.user.notifications.find(notif => notif.id === notifId && !notif.read)){
        // console.log('inside')
        try {
            const response = await axios.patch(
                route('grp.models.notifications.read', notifId)
            )
    
        } catch (error: any) {
            console.log(error)
            notify({
                title: 'Error on set notifications.',
                text: error,
                type: 'error'
            })
        }
    }
}

let timer: ReturnType<typeof setTimeout> | null = null
onMounted(async () => {
    timer = setTimeout(async () => {
        layout.user.notifications.map(notif => setNotificationToRead(notif.id))
        timer = null
    }, 2500)
})

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer)
    }
})
</script>

<template>
    <div class="flex items-center flex-col w-full overflow-auto min-h-11 max-h-96">
        <div @click="() => layout.user.notifications.every(notif => notif.read) ? false : setAllToRead()"
            class="place-self-end  text-sm select-none"
            :class="layout.user.notifications.every(notif => notif.read) ? 'text-gray-400 cursor-not-allowed' : 'text-indigo-500 hover:text-indigo-500 cursor-pointer'"    
        >
            <LoadingIcon v-if="isLoading" />
            {{ trans('Marks all as read') }}
        </div>
        
        <ul v-if="layout.user.notifications.length" role="list" class="w-full divide-y divide-gray-100 overflow-y-auto">
            <li v-for="notif in layout.user.notifications" :key="notif.id"
                class="relative flex justify-between gap-x-6 px-1 py-2 hover:bg-gray-50 sm:px-2">
                <Transition name="spin-to-down">
                    <FontAwesomeIcon
                        :key="notif.id + notif.read"
                        :icon="notif.read ? ['fal', 'envelope-open-text'] : ['fal', 'envelope']"
                        class='text-3xl m-auto'
                        :class="notif.read ? 'text-gray-300' : 'text-gray-400'"
                        fixed-width
                        aria-hidden='true'
                    />
                </Transition>
                <div class="min-w-0 flex-auto relative">
                    <div class="text-sm font-semibold leading-6" :class="[notif.read ? 'text-gray-400' : '']">
                        <component
                            :is="notif.route ? Link : 'div'"
                            :href="notif.route"
                            @success="() => (notif.read ?? setNotificationToRead(notif.id), props.close())"
                        >
                            <span class="absolute inset-x-0 -top-px bottom-0"></span>
                            {{ notif.title }}
                        </component>
                    </div>
                    <span class="text-[10px] text-gray-500 absolute top-0 right-0 mt-1 mr-1">
                        {{ useFormatTime(notif.created_at) }}
                    </span>
                    <p :class="['mt-1 flex text-xs leading-5 truncate', notif.read ? 'text-gray-400' : 'text-gray-500']">
                        {{ notif.body }}
                    </p>
                </div>
            </li>
        </ul>

        <div v-else class="mx-auto italic text-gray-400">
            {{ trans('You have no new notifications') }}.
        </div>

        <div class="flex w-full justify-center border-t border-gray-200 mt-3 pt-3">
            <div @click="() => (close(), layout.stackedComponents.push({ component: Profile, data: { currentTab: 'notifications' }}))" class="cursor-pointer px-2 text-gray-400 hover:text-gray-500 font-semibold">
                {{ trans('Show all notification') }}
            </div>
        </div>
    </div>
</template>
