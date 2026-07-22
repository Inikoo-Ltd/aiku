<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 19 Aug 2021 18:54:53 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2021, Inikoo
  -  Version 4.0
  -->
<script setup lang="ts">
import { ref, computed, onMounted } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { Menu, MenuButton, MenuItems, MenuItem } from "@headlessui/vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronRight } from '@far'
import { faBars,faBallot, faBookmark, faTrashAlt } from '@fal'
import { faSparkles, faArrowFromLeft, faArrowLeft, faArrowRight, faBookmark as fasBookmark } from '@fas'
import { routeType } from '@/types/route'
import { Bookmark } from '@/types/Bookmark'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Popover from '@/Components/Popover.vue'

library.add(faSparkles, faArrowFromLeft, faArrowLeft, faArrowRight, faChevronRight, faBars,faBallot, faBookmark, faTrashAlt, fasBookmark)

const props = defineProps<{
    breadcrumbs: {
        type: string
        simple: {
            icon?: string
            overlay?: string
            label?: string
            route?: routeType
            url?: string
        }
        creatingModel: {
            label?: string
        }
        modelWithIndex: {
            index: {
                icon?: string
                label?: string
                route?: routeType
                url?: string
                suffix?: string
            }
            model: {
                icon?: string
                label?: string
                route?: routeType
                url?: string
            }
        }
        suffix?: string
        options?: object
    }[]

    navigation?: {
        next?: {
            label?: string,
            route?: routeType
            url?: string
        }
        previous?: {
            label?: string,
            route?: routeType
            url?: string
        }
    }
    layout?: any  // useLayoutStore
}>()

// Get parameter for Prev & Next button to stay on same tab
const urlParameter = ref('showcase')
const currentPath = ref('')

const getFilteredQueryString = () => {
    const params = new URLSearchParams(location.search.substring(1))
    const filteredParams: {[key:string]: string} = {}
    const patternToDelete = /\[global\]$/  // to filter su_filter[global], etc
    for (const [key, value] of params.entries()) {
        if (!patternToDelete.test(key)) {
            filteredParams[key] = value
        }
    }
    return new URLSearchParams(filteredParams).toString()
}

router.on('navigate', () => {
    urlParameter.value = `?${getFilteredQueryString()}`
    currentPath.value = location.pathname
})

const isLoading = ref<string | boolean>(false)

// Section: Bookmarks, persisted on the user model and hydrated from layout props on first load
const isBookmarkAvailable = computed(() => Array.isArray(props.layout?.bookmarks))
const bookmarks = computed<Bookmark[]>(() => props.layout?.bookmarks ?? [])
const isSavingBookmarks = ref(false)

onMounted(() => {
    currentPath.value = location.pathname
})

const persistBookmarks = async (nextBookmarks: Bookmark[]) => {
    const previousBookmarks = [...bookmarks.value]
    props.layout.bookmarks = nextBookmarks
    isSavingBookmarks.value = true

    try {
        await axios.patch(route('grp.profile.bookmarks.update'), { bookmarks: nextBookmarks })
    } catch (error) {
        props.layout.bookmarks = previousBookmarks
        notify({
            title: trans('Something went wrong'),
            text: trans('Failed to save bookmarks'),
            type: 'error'
        })
    } finally {
        isSavingBookmarks.value = false
    }
}

const isCurrentPageBookmarked = computed(() => {
    return bookmarks.value.some(bookmark => bookmark.url.split('?')[0] === currentPath.value)
})

const getCurrentPageLabel = () => {
    const lastBreadcrumb = props.breadcrumbs?.[props.breadcrumbs.length - 1]
    return lastBreadcrumb?.simple?.label
        || lastBreadcrumb?.modelWithIndex?.model?.label
        || lastBreadcrumb?.creatingModel?.label
        || document.title
}

const toggleBookmarkCurrentPage = () => {
    if (isSavingBookmarks.value) {
        return
    }

    if (isCurrentPageBookmarked.value) {
        persistBookmarks(bookmarks.value.filter(bookmark => bookmark.url.split('?')[0] !== currentPath.value))
        return
    }

    const queryString = getFilteredQueryString()
    persistBookmarks([
        ...bookmarks.value,
        {
            label: getCurrentPageLabel(),
            url: currentPath.value + (queryString ? `?${queryString}` : ''),
            shop: props.layout?.currentParams?.shop,
            organisation: props.layout?.currentParams?.organisation
        }
    ])
}

const getBookmarkSubtitle = (bookmark: Bookmark) => {
    return [bookmark.organisation, bookmark.shop].filter(Boolean).join(' / ')
}

const removeBookmark = (bookmarkToRemove: Bookmark) => {
    if (isSavingBookmarks.value) {
        return
    }

    persistBookmarks(bookmarks.value.filter(bookmark => bookmark.url !== bookmarkToRemove.url))
}
</script>

<template>
    <nav class="isolate relative xxxoverflow-y-hidden flex text-gray-600 h-8 xl:h-8 border-b border-gray-200 text-xs md:text-sm" aria-label="Breadcrumb">
        <!-- Breadcrumb -->
        <TransitionGroup name="list-to-down" tag="ol" class="w-full mx-auto md:px-4 flex">
            <li v-for="(breadcrumb, breadcrumbIdx) in breadcrumbs" :key="breadcrumbIdx"
                class="hidden first:flex last:flex md:flex items-center">
        
                <div class="flex items-center">
                    <!-- Shorter Breadcrumb on Mobile size -->
                    <div v-if="breadcrumbs.length > 2 && breadcrumbIdx != 0" class="md:hidden flex items-center">
                        <FontAwesomeIcon v-if="breadcrumbIdx !== 0" class="flex-shrink-0 h-3 w-3 mx-3 opacity-50" icon="fa-regular fa-chevron-right" aria-hidden="true" />
                        <span>...</span>
                    </div>
                    
                    <template v-if="breadcrumb.type === 'simple'">
                        <FontAwesomeIcon v-if="breadcrumbIdx !== 0" class="flex-shrink-0 h-3 w-3 mx-3 opacity-50" icon="fa-regular fa-chevron-right" aria-hidden="true" />
                        <component
                            :is="breadcrumb.simple.url || breadcrumb.simple.route?.name ? Link : 'span'"
                            xclass="'' || ''"
                            :href="breadcrumb.simple.url ? breadcrumb.simple.url : breadcrumb.simple?.route?.name ? route( breadcrumb.simple.route.name, breadcrumb.simple.route.parameters ) : '#' "
                            class="hover:text-gray-700 overflow-hidden flex items-center"
                        >
                            <Transition name="spin-to-down">
                                <FontAwesomeIcon v-if="breadcrumb.simple?.icon" :class="breadcrumb.simple.label ? 'mr-1' : ''" fixed-width class="flex-shrink-0 h-3.5 w-3.5" :icon="breadcrumb.simple.icon" aria-hidden="true" />
                            </Transition>
                            
                            <Transition name="spin-to-down">
                                <div v-if="breadcrumb.simple.label" :key="breadcrumb.simple.label" class="inline-block truncate py-1 md:py-0 max-w-[50vw] sm:w-auto">{{ breadcrumb.simple.label }}</div>
                            </Transition>
                        </component>
                    </template>
                    
                    <!-- Section: Create Model -->
                    <template v-else-if="breadcrumb.type === 'creatingModel'">
                        <FontAwesomeIcon class="flex-shrink-0 h-3.5 w-3.5 mr-1 text-yellow-500 ml-2" icon="fas fa-sparkles" aria-hidden="true" />
                        <span class="text-yellow-600 opacity-75"> {{ breadcrumb.creatingModel.label }}</span>
                    </template>
                    <template v-else-if="breadcrumb.type === 'modelWithIndex'">
                        <div class="hidden md:inline-flex">
                            <FontAwesomeIcon v-if="breadcrumbIdx !== 0" class="flex-shrink-0 h-3 w-3 mx-3 opacity-50 place-self-center" icon="fa-regular fa-chevron-right" aria-hidden="true" />
                            <component :is="breadcrumb.modelWithIndex?.index?.url || breadcrumb.modelWithIndex?.index?.route?.name ? Link : 'div'"  class="hover:text-gray-700 grid grid-flow-col items-center"
                                :href="breadcrumb.modelWithIndex?.index?.url ? breadcrumb.modelWithIndex?.index?.url : breadcrumb.modelWithIndex?.index?.route?.name ? route(breadcrumb.modelWithIndex.index.route.name, breadcrumb.modelWithIndex.index.route.parameters) : '#' ">
                                <FontAwesomeIcon icon="fal fa-bars" class="flex-shrink-0 h-3.5 w-3.5 mr-1" aria-hidden="true" />
                                <span>{{ breadcrumb.modelWithIndex.index.label }}</span> <span v-if="breadcrumb.modelWithIndex.index.suffix" class="ml-1 italic"> {{ breadcrumb.modelWithIndex.index.suffix }} </span>
                            </component>
                        </div>
                        <span class="mx-3 select-none">→</span>
                        <component
                            :is="breadcrumb.modelWithIndex?.model?.url || breadcrumb.modelWithIndex?.model?.route?.name ? Link : 'div'" class="breadcrumbSection"
                            :href="breadcrumb.modelWithIndex?.model?.url ? breadcrumb.modelWithIndex?.model?.url : breadcrumb.modelWithIndex?.model?.route?.name ? route(breadcrumb.modelWithIndex.model.route.name, breadcrumb.modelWithIndex.model.route.parameters) : '#'">
                            {{ breadcrumb.modelWithIndex.model.label }}
                        </component>
                    </template>
                    <span v-if="breadcrumb.suffix" :class="breadcrumb.type ? 'ml-1' : ''" class="italic">{{ breadcrumb.suffix }}</span>
                </div>
            </li>
        </TransitionGroup>

        <!-- Popup for Breadcrumb List on Mobile -->
        <Menu as="div" class="z-50 w-fit h-8 absolute top-0 md:hidden">
            <MenuButton class="absolute w-64 h-full"></MenuButton>
            <transition enter-active-class="transition ease-out duration-100"
                enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100"
                leave-to-class="transform opacity-0 scale-95">
                <MenuItems
                    class="origin-top-right absolute left-4 top-9 w-64 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-200 focus:outline-none">
                    <MenuItem v-for="(breadcrumb, breadcrumbIdx) in breadcrumbs" :key="breadcrumbIdx" class="">
                    <template v-if="breadcrumb.type === 'simple'">
                        <component :is="breadcrumb.simple?.url || breadcrumb.simple?.route?.name ? Link : 'span'"
                            xclass="'' || ''"
                            class="py-2 grid grid-flow-col items-center justify-start"
                            :href="breadcrumb.simple?.url ? breadcrumb.simple?.url : breadcrumb.simple?.route?.name ? route(breadcrumb.simple.route.name, breadcrumb.simple.route.parameters) : ''"
                            :style="{ paddingLeft: 12 + breadcrumbIdx * 7 + 'px' }"
                        >
                            <!-- Icon Section -->
                            <FontAwesomeIcon v-if="breadcrumb.simple.icon && breadcrumbIdx == 0" class="flex-shrink-0 h-3.5 w-3.5" :icon="breadcrumb.simple.icon" aria-hidden="true" />

                            <!-- Icon Arrow -->
                            <FontAwesomeIcon v-if="breadcrumbIdx != 0" class="flex-shrink-0 h-3.5 w-3.5 text-gray-300" icon="fa fa-arrow-from-left" aria-hidden="true" />
                            <span v-if="breadcrumbIdx == 0 && !breadcrumb.simple.label" class="grid grid-flow-cols justify-center font-bold ml-2">
                                DASHBOARD
                            </span>
                            <span class="grid grid-flow-col items-center ml-4 mr-3">
                                {{ breadcrumb.simple.label }}
                            </span>

                            <!-- Icon List (Simple) -->
                            <FontAwesomeIcon v-if="breadcrumb.simple.icon && breadcrumbIdx != 0" class="flex-shrink-0 h-3.5 w-3.5" :icon="breadcrumb.simple.icon" aria-hidden="true" />
                        </component>
                    </template>

                    <template v-else-if="breadcrumb.type === 'creatingModel'">
                        <span class="text-yellow-600 opacity-75">
                            {{ breadcrumb.creatingModel.label }}
                        </span>
                    </template>

                    <template v-else-if="breadcrumb.type === 'modelWithIndex'">
                        <div class="divide-y divide-gray-200">
                            <component :is="breadcrumb.modelWithIndex?.index?.url || breadcrumb.modelWithIndex?.index?.route?.name ? Link : 'div'" class="py-2 grid grid-flow-col justify-start items-center"
                                :href="breadcrumb.modelWithIndex?.index?.url ? breadcrumb.modelWithIndex?.index?.url : breadcrumb.modelWithIndex?.index?.route?.name ? route(breadcrumb.modelWithIndex.index.route.name, breadcrumb.modelWithIndex.index.route.parameters) : '#' "
                                :style="{ paddingLeft: 12 + breadcrumbIdx * 7 + 'px' }"
                            >
                                <FontAwesomeIcon class="flex-shrink-0 h-3.5 w-3.5 text-gray-300" icon="fa fa-arrow-from-left" aria-hidden="true" />
                                <span class="md:text-xs ml-4 mr-3">
                                    {{ breadcrumb.modelWithIndex.index.label }}
                                </span>

                                <!-- Icon List -->
                                <FontAwesomeIcon :icon="['fal', 'bars']" class="flex-shrink-0 h-3.5 w-3.5" aria-hidden="true" />
                            </component>

                            <!-- Subpage -->
                            <component :is="breadcrumb.modelWithIndex?.model.url || breadcrumb.modelWithIndex?.model?.route?.name ? Link : 'div'"  class="py-2 grid grid-flow-col justify-start items-center text-indigo-400"
                                :href="breadcrumb.modelWithIndex?.model.url ? breadcrumb.modelWithIndex?.model.url : breadcrumb.modelWithIndex?.model?.route?.name ? route(breadcrumb.modelWithIndex.model.route.name, breadcrumb.modelWithIndex.model.route.parameters) : '#'"
                                :style="{ paddingLeft: 12 + (breadcrumbIdx + 1) * 7 + 'px', }"
                            >
                                <FontAwesomeIcon class="flex-shrink-0 h-3.5 w-3.5 mr-1 text-gray-300" icon="fa fa-arrow-from-left" aria-hidden="true" />
                                <span class="ml-4 mr-3">
                                    {{ breadcrumb.modelWithIndex.model.label }}
                                </span>
                            </component>
                        </div>
                    </template>
                    </MenuItem>
                </MenuItems>
            </transition>
        </Menu>

        <div class="h-full flex justify-end items-center pr-2 space-x-2 text-xs md:text-sm text-gray-700 font-semibold">
            <!-- Button: Bookmark -->
            <div v-if="isBookmarkAvailable" class="relative flex justify-center items-center w-12 xl:w-8 h-full">
                <Popover class="w-full h-full" position="right-0" width="w-64">
                    <template #button>
                        <div class="rounded w-full h-full flex items-center justify-center cursor-pointer hover:text-indigo-500"
                            :class="isCurrentPageBookmarked ? 'text-indigo-500' : 'opacity-70 hover:opacity-100'"
                            v-tooltip="isCurrentPageBookmarked ? trans('Remove bookmark') : trans('Bookmark this page')"
                        >
                            <LoadingIcon v-if="isSavingBookmarks" />
                            <FontAwesomeIcon v-else :icon="isCurrentPageBookmarked ? 'fas fa-bookmark' : 'fal fa-bookmark'" aria-hidden="true" />
                        </div>
                    </template>

                    <template #content="{ close }">
                        <div class="w-full text-left font-normal">
                            <button
                                @click="toggleBookmarkCurrentPage"
                                class="w-full flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-100 cursor-pointer"
                            >
                                <FontAwesomeIcon :icon="isCurrentPageBookmarked ? 'fas fa-bookmark' : 'fal fa-bookmark'" class="flex-shrink-0 h-3.5 w-3.5" :class="isCurrentPageBookmarked ? 'text-indigo-500' : ''" aria-hidden="true" />
                                <span>{{ isCurrentPageBookmarked ? trans('Remove bookmark') : trans('Bookmark this page') }}</span>
                            </button>

                            <div class="border-t border-gray-200 my-2" />

                            <div v-if="!bookmarks.length" class="px-2 py-1.5 text-gray-400 italic">
                                {{ trans('No bookmarks yet') }}
                            </div>

                            <div v-else class="max-h-64 overflow-y-auto space-y-0.5">
                                <div v-for="bookmark in bookmarks" :key="bookmark.url" class="flex items-center gap-1 px-2 py-1 rounded hover:bg-gray-100">
                                    <Link :href="bookmark.url" @click="close" class="flex-1 min-w-0 py-0.5" v-tooltip="bookmark.label">
                                        <div class="truncate hover:text-indigo-600">{{ bookmark.label }}</div>
                                        <div v-if="getBookmarkSubtitle(bookmark)" class="truncate text-[10px] leading-3 text-gray-400">
                                            {{ getBookmarkSubtitle(bookmark) }}
                                        </div>
                                    </Link>
                                    <button @click="removeBookmark(bookmark)" class="flex-shrink-0 p-1 text-red-500 opacity-50 hover:opacity-100 cursor-pointer" v-tooltip="trans('Remove bookmark')">
                                        <FontAwesomeIcon icon="fal fa-trash-alt" class="h-3 w-3" aria-hidden="true" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </Popover>
            </div>

            <!-- Button: Previous -->
            <div class="flex justify-center items-center w-12 xl:w-8 h-full">
                <Link v-if="props.navigation.previous"
                    @start="() => isLoading = 'bcBack'"
                    @finish="() => isLoading = false"
                    :href="isLoading === 'bcBack' ? '' : props.navigation?.previous?.url ? props.navigation?.previous?.url : props.navigation?.previous?.route?.name ? route(props.navigation.previous?.route.name, props.navigation.previous?.route.parameters) + urlParameter : '#'"
                    class="rounded w-full h-full flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer hover:text-indigo-500"
                    :title="props.navigation.previous?.label"
                    :aria-label="ctrans('Previous')"
                >
                    <LoadingIcon v-if="isLoading === 'bcBack'" />
                    <FontAwesomeIcon v-else icon="fas fa-arrow-left" class="" aria-hidden="true" />
                </Link>
                <FontAwesomeIcon v-else icon="fas fa-arrow-left" class="opacity-20" aria-hidden="true" />
            </div>

            <!-- Button: Next -->
            <div class="flex justify-center items-center w-12 xl:w-8 h-full">
                <Link v-if="props.navigation.next"
                    @start="() => isLoading = 'bcNext'"
                    @finish="() => isLoading = false"
                    class="rounded w-full h-full flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer hover:text-indigo-500"
                    :title="props.navigation.next?.label"
                    :aria-label="ctrans('Next')"
                    :href="isLoading === 'bcNext' ? '' : props.navigation?.next?.url ? props.navigation?.next?.url : props.navigation?.next?.route?.name ? route(props.navigation.next?.route.name, props.navigation.next?.route.parameters) + urlParameter : '#'"
                >
                    <LoadingIcon v-if="isLoading === 'bcNext'" />
                    <FontAwesomeIcon v-else icon="fas fa-arrow-right" class="" aria-hidden="true" />
                </Link>
                <FontAwesomeIcon v-else icon="fas fa-arrow-right" class="opacity-20" aria-hidden="true" />
            </div>
        </div>
    </nav>
</template>

<style lang="scss" scope>
.breadcrumbSection {
    color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[0]} 90%, white)`') !important;

    &:hover {
        color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[0]} 85%, black)`') !important;
    }
}

</style>