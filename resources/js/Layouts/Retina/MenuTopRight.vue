<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { Link } from '@inertiajs/vue3'
import { inject, onMounted, onUnmounted, ref } from 'vue'
import SearchBar from "@/Components/SearchBar.vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Image from '@/Components/Image.vue'


const showSearchDialog = ref(false)

const layout = inject('layout')

onMounted(() => {
    if (typeof window !== 'undefined') {
        document.addEventListener('keydown', (event) => {

            if( ( isUserMac ? event.metaKey : event.ctrlKey ) && event.key === 'k') {
                event.preventDefault()
                showSearchDialog.value = !showSearchDialog.value
            }
        })
    }
})

onUnmounted(() => {
    document.removeEventListener('keydown', () => false)
})
const isUserMac = navigator.platform.includes('Mac')  // To check the user's Operating System
console.log(layout)
</script>

<template>
    <!-- Avatar Group -->
    <div class="flex justify-between items-center gap-x-4">
            <button @click="showSearchDialog = !showSearchDialog" id="search"
                class="h-8 w-fit flex items-center justify-center gap-x-3 ring-1 ring-gray-300 rounded-md px-3 text-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500"
                :class="showSearchDialog ? 'bg-gray-700/20' : 'hover:bg-gray-200 hover:text-gray-500'"
            >
                <span class="sr-only">{{ trans("Search") }}</span>
                <FontAwesomeIcon aria-hidden="true" size="sm" icon="fa-regular fa-search" />
                <div class="hidden whitespace-nowrap md:flex items-center justify-end text-gray-500/80 tracking-tight space-x-1">
                    <span v-if="isUserMac" class="ring-1 ring-gray-400 bg-gray-100 px-2 leading-none text-xl rounded">⌘</span>
                    <span v-else class="ring-1 ring-gray-400 bg-gray-100 px-2 py-0.5 text-xs rounded">Ctrl</span>
                    <span class="ring-1 ring-gray-400 bg-gray-100 px-1.5 py-0.5 text-xs rounded">K</span>
                </div>
                <SearchBar v-model="showSearchDialog" />
            </button>

            <!-- Avatar Button -->
            <Link :href="route('retina.profile.show')"
                id="avatar-thumbnail"
                class="pl-3 pr-1 flex gap-x-2 items-center rounded-full"
                :class="layout?.currentRoute.includes('retina.profile.show') ? 'bg-gray-200 ring-1 ring-gray-300' : 'hover:bg-gray-200'">
                <div class="text-gray-700 text-lg truncate max-w-[145px]" :title="`${layout?.user?.username} from ${layout?.customer?.company_name}`">
                    {{ layout?.webUser_count > 1 ? `${layout?.user?.username}` :  layout?.customer?.company_name }}
                </div>
                <span class="sr-only">{{ trans("Open user menu") }}</span>
                <div class="h-8 aspect-square rounded-full overflow-hidden border border-gray-300">
                    <Image v-if="layout?.user?.avatar_thumbnail" :src="layout.user?.avatar_thumbnail" alt="" />
                    <img v-else src="/retina-default-user.svg" alt="Retina default avatar" class="p-0.5">
                </div>
            </Link>


    </div>
</template>