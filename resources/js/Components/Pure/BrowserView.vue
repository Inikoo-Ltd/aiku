<script setup lang="ts">
import { ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
    faCircle,
    faArrowLeft,
    faArrowRight,
    faRedoAlt,
    faPuzzlePiece,
    faPlus,
    faStar as fasStar,
    faDesktop,
    faTabletAlt,
    faMobileAlt
} from '@fas'
import {
    faGlobe,
    faEllipsisV,
    faStar,
} from '@fal'
import { faTimes } from '@far'
import { faChrome } from '@fortawesome/free-brands-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'

library.add(
    faCircle, faArrowLeft, faArrowRight, faRedoAlt, faPuzzlePiece, faPlus,
    fasStar, faStar, faEllipsisV, faGlobe, faTimes, faChrome, faDesktop,
    faTabletAlt, faMobileAlt
)

const props = defineProps<{
    tab?: { icon?: string | string[]; label?: string }
    url?: { domain?: string; page?: string }
    screenMode? : string
}>()

const isStar = ref(false)
const isLoadingRefreshPage = ref(false)
const keyIconTimes = ref(0)

const onRefreshPage = () => {
    isLoadingRefreshPage.value = true
    setTimeout(() => {
        isLoadingRefreshPage.value = false
    }, 1000)
}

const getAspectClass = () => {
    if (props.screenMode === 'mobile') return 'aspect-[9/16] w-[320px]'
    if (props.screenMode === 'tablet') return 'aspect-[3/4] w-[768px]'
    return 'aspect-[16/9] w-full'
}
</script>

<template>
    <div class="flex flex-col items-center space-y-4">
        <!-- Browser Container -->
        <div class="rounded-xl shadow-xl overflow-hidden bg-white border border-gray-300" :class="getAspectClass()">
            <!-- Browser Header -->
            <div class="flex items-center bg-gray-100 px-3 py-1 space-x-2">
                <div class="flex space-x-1">
                    <FontAwesomeIcon icon="fas fa-circle" class="text-red-400" />
                    <FontAwesomeIcon icon="fas fa-circle" class="text-yellow-400" />
                    <FontAwesomeIcon icon="fas fa-circle" class="text-green-400" />
                </div>
                <div class="flex items-center gap-x-2 border border-gray-300 rounded-full px-4 py-0.5 bg-white max-w-48 w-full">
                    <div class="flex items-center space-x-2 truncate min-w-0">
                        <FontAwesomeIcon :icon="tab?.icon || 'fab fa-chrome'" />
                        <span class="truncate text-sm font-medium">{{ tab?.label || trans('New Tab') }}</span>
                    </div>
                    <div class="ml-auto cursor-pointer opacity-50 hover:opacity-100" @click="() => keyIconTimes++">
                        <Transition name="spin-to-right">
                            <FontAwesomeIcon :key="keyIconTimes" icon="far fa-times" />
                        </Transition>
                    </div>
                </div>
            </div>

            <!-- Navigation Bar -->
            <div class="flex items-center justify-between bg-white px-3 py-2 border-t border-b space-x-2">
                <!-- Nav Controls -->
                <div class="flex items-center space-x-3 shrink-0">
                    <FontAwesomeIcon icon="far fa-arrow-left" class="text-gray-500 cursor-pointer" />
                    <FontAwesomeIcon icon="far fa-arrow-right" class="text-gray-500 cursor-pointer" />
                    <Transition name="spin-to-right">
                        <FontAwesomeIcon v-if="!isLoadingRefreshPage" @click="onRefreshPage" fixed-width icon="fas fa-redo-alt"
                            class="text-gray-500 cursor-pointer" />
                        <FontAwesomeIcon v-else fixed-width icon="far fa-times" class="text-gray-500 cursor-pointer"
                            @click="isLoadingRefreshPage = false" />
                    </Transition>
                </div>

                <!-- Search Bar -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center bg-gray-100 px-4 py-1 rounded-full w-full">
                        <FontAwesomeIcon icon="fal fa-globe" class="text-xs shrink-0" />
                        <span class="truncate text-sm ml-2 text-gray-700">
                            <template v-if="url?.page && url.page.includes('http')">
                                {{ url.page }}
                            </template>
                            <template v-else>
                                {{ url?.domain || 'www.website.com' }}<span class="text-gray-400">/{{ url?.page }}</span>
                            </template>
                        </span>
                        <FontAwesomeIcon :icon="isStar ? 'fas fa-star' : 'fal fa-star'"
                            class="ml-auto text-yellow-500 cursor-pointer" @click="isStar = !isStar" />
                    </div>
                </div>

                <!-- Menu Controls -->
                <div class="flex items-center space-x-2 shrink-0">
                    <FontAwesomeIcon icon="fas fa-puzzle-piece" />
                    <FontAwesomeIcon icon="fal fa-ellipsis-v" />
                </div>
            </div>

            <!-- Page Preview -->
            <div class="relative h-full w-full bg-white overflow-hidden">
                <slot name="page">
                    <div class="w-full h-full bg-indigo-100 flex items-center justify-center text-gray-600">
                        <span class="text-sm">Page Preview</span>
                    </div>
                </slot>
            </div>
        </div>
    </div>
</template>



<style scoped>
.spin-to-right-enter-active,
.spin-to-right-leave-active {
    transition: transform 0.5s ease;
}

.spin-to-right-enter-from {
    transform: rotate(-180deg);
}

.spin-to-right-enter-to {
    transform: rotate(0deg);
}

.spin-to-right-leave-from {
    transform: rotate(0deg);
}

.spin-to-right-leave-to {
    transform: rotate(180deg);
}
</style>
