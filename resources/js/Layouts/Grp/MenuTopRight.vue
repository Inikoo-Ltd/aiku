<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { ref, onMounted, onUnmounted, inject, defineAsyncComponent } from 'vue'
import SearchBar from "@/Components/SearchBar.vue"
import Image from '@/Components/Image.vue'
import Popover from '@/Components/Popover.vue'
import NotificationList from '@/Components/NotificationList/NotificationList.vue'
import Profile from "@/Pages/Grp/Profile.vue"

import Button from "@/Components/Elements/Buttons/Button.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCircle } from '@fas'
import { faSparkles } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import AskBot from '@/Components/AskBot.vue'
import { faLampDesk } from '@fal'
library.add(faCircle, faLampDesk, faSparkles)

/* const Profile = defineAsyncComponent(() => import("@/Pages/Grp/Profile.vue")) */


const props = defineProps<{
    urlPrefix: string
}>()

const layout = inject('layout', layoutStructure)
const isAskBotEnabled =  import.meta.env.VITE_ASK_BOT_UI;
// const layoutStore = useLayoutStore()
const showSearchDialog = ref(false)
const showAskBot = ref(false)

onMounted(() => {
    if (typeof window !== 'undefined') {
        document.addEventListener('keydown', (event) => {
            
            if( ( isUserMac ? event.metaKey : event.ctrlKey ) && event.key === 'k') {
                event.preventDefault()
                showSearchDialog.value = !showSearchDialog.value
            }
            if ((isUserMac ? event.metaKey : event.ctrlKey) && event.shiftKey && event.key === 'K') {
                event.preventDefault();
                showAskBot.value = !showAskBot.value;
            }
        })
    }
})

onUnmounted(() => {
    document.removeEventListener('keydown', () => false)
})

const isUserMac = navigator.platform.includes('Mac')  // To check the user's Operating System

</script>

<template>
    <!-- Avatar Group -->
    <div class="flex justify-between gap-x-2">
        <div class="flex items-center gap-x-0 sm:gap-x-4 sm:divide-x divide-gray-200">
            <!-- Button: Search -->
            <button @click="showSearchDialog = !showSearchDialog" id="search"
                class="h-7 w-fit flex items-center justify-center gap-x-3 ring-1 ring-gray-300 rounded-md px-3 text-gray-500 hover:bg-gray-200 hover:text-gray-500 focus:outline-hidden focus:ring-2 focus:ring-gray-500">
                <span class="sr-only">{{ trans("Search") }}</span>
                <FontAwesomeIcon aria-hidden="true" size="sm" icon="fa-regular fa-search" />
                <div class="hidden whitespace-nowrap md:flex items-center justify-end text-gray-500/80 tracking-tight space-x-1">
                    <span v-if="isUserMac" class="ring-1 ring-gray-400 bg-gray-100 px-2 leading-none text-xl rounded">⌘</span>
                    <span v-else class="ring-1 ring-gray-400 bg-gray-100 px-2 py-0.5 text-xs rounded">Ctrl</span>
                    <span class="ring-1 ring-gray-400 bg-gray-100 px-1.5 py-0.5 text-xs rounded">K</span>
                </div>
                <SearchBar v-model="showSearchDialog" />
            </button>
            
            <!-- Search: AI -->
            <button v-if="true" @click="showAskBot = !showAskBot" id="ask-bot"
                class="bg-gradient-to-tr from-pink-200 xvia-pink-200 to-pink-100 border-none ring-1 ring-fuchsia-400 h-7 w-fit flex items-center justify-center gap-x-3 rounded-md px-3">
                <div class="flex gap-x-1 items-center ">
                    <FontAwesomeIcon icon="fas fa-sparkles" class="text-pink-500" fixed-width aria-hidden="true" />
                    <h1 class="xanimate-linear xbg-gradient-to-r xfrom-fuchsia-300 text-pink-500 xto-fuchsia-300 xbg-[length:200%_auto] xbg-clip-text font-bold xtext-transparent">
                        <span class="">AI</span>
                    </h1>
                </div>
                
                <!-- <FontAwesomeIcon icon='fal fa-lamp-desk' size="sm" class='ml-1 text-gray-600' fixed-width aria-hidden='true' /> -->
                <div class="hidden whitespace-nowrap md:flex items-center justify-end text-gray-500/80 tracking-tight space-x-1">
                    <span v-if="isUserMac" class="ring-1 ring-fuchsia-400 bg-fuchsia-50 text-fuchsia-500 px-2 leading-none text-xl rounded">⌘</span>
                    <span v-else class="ring-1 ring-fuchsia-400 bg-fuchsia-50 text-fuchsia-500 px-2 py-0.5 text-xs rounded">Ctrl</span>
                    <span class="ring-1 ring-fuchsia-400 bg-fuchsia-50 text-fuchsia-500 px-2 py-0.5 text-xs rounded">Shift</span>
                    <span class="ring-1 ring-fuchsia-400 bg-fuchsia-50 text-fuchsia-500 px-1.5 py-0.5 text-xs rounded">K</span>
                </div>
                <AskBot v-model="showAskBot" />
            </button>

            <div class="pl-2 sm:pl-4 flex items-center gap-x-2">
                <div @click="() => layout.stackedComponents.push({ component: Profile, data: { currentTab: 'todo' }})">
                    <Button label="To do" size="xs" :style="'tertiary'" />
                </div>

                <!-- Button: Notifications -->
                <div class="relative px-2 rounded-full flex items-center">
                    <Popover>
                        <template #button>
                            <div tabindex="-1" class="relative text-gray-400 hover:text-gray-500 focus:outline-hidden focus:ring-2 focus:ring-gray-500">
                                <FontAwesomeIcon aria-hidden="true" icon="fa-regular fa-bell" size="lg" />
                                <FontAwesomeIcon v-if="layout?.notifications?.some(notif => !notif.read)" icon='fas fa-circle' class='animate-pulse text-blue-500 absolute top-[1px] -right-0.5 text-[6px]' fixed-width aria-hidden='true' />
                            </div>
                        </template>
                        <template #content="{ close }">
                            <div class="w-[450px]">
                                <NotificationList :close />
                            </div>
                        </template>
                    </Popover>
                </div>

                <!-- Button: Profile -->
                <div @click="layout.stackedComponents.push({ component: Profile})"
                    class="flex max-w-xs overflow-hidden items-center rounded-full bg-gray-100 text-sm focus:outline-hidden focus:ring-2 focus:ring-gray-500 cursor-pointer">
                    <span class="sr-only">{{ trans("Open user menu") }}</span>
                    <Image class="h-8 w-8 rounded-full" :src="layout.avatar_thumbnail" alt="" />
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.underline-gradient {
    background-image: linear-gradient(90deg, #bae6fd, #a78bfa, theme('colors.pink.500'), #a78bfa, #bae6fd);
}
</style>