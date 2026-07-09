<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 19 Aug 2021 18:54:53 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2021, Inikoo
  -  Version 4.0
  -->
<script setup lang="ts">
import { nextTick, ref, onUnmounted, computed } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { Menu, MenuButton, MenuItems, MenuItem } from "@headlessui/vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronRight } from '@far'
import { faBars,faBallot } from '@fal'
import { faSparkles, faArrowFromLeft, faArrowLeft, faArrowRight } from '@fas'
import { routeType } from '@/types/route'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { onMounted } from "vue"
import { watch } from "vue"
import { useBreadcrumbStructuredData } from "@/Iris/Composables/useBreadcrumbStructuredData"
import { Popover } from "primevue"

library.add(faSparkles, faArrowFromLeft, faArrowLeft, faArrowRight, faChevronRight, faBars,faBallot)

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
router.on('navigate', (event) => {
    const params = new URLSearchParams(location.search.substring(1))
    const filteredParams: {[key:string]: string} = {}
    const patternToDelete = /\[global\]$/  // to filter su_filter[global], etc
    for (const [key, value] of params.entries()) {
        if (!patternToDelete.test(key)) {
            filteredParams[key] = value
        }
    }
    urlParameter.value = `?${new URLSearchParams(filteredParams).toString()}`
})

const isLoading = ref<string | boolean>(false)


// Section: to scroll to the end of breadcrumb list (if breadcrumb too long)
const scrollerRef = ref<HTMLElement | null>(null)
const scrollToRight = () => {
    const el = scrollerRef.value
    if (!el) return

    const a = el.scrollWidth - el.clientWidth
    setTimeout(() => {
        // el.scrollLeft = 2000
        el.scrollTo({ left: 2000, behavior: 'smooth' })
    }, 100)
}
onMounted(async () => {
    await nextTick()
    scrollToRight()
})
watch(() => props.breadcrumbs?.length, async () => {
    await nextTick()
    scrollToRight()
})

const isMobileRef = ref(false)

let mediaQuery: MediaQueryList | null = null
let handler: ((e: MediaQueryListEvent) => void) | null = null

onMounted(() => {
  mediaQuery = window.matchMedia("(max-width: 768px)")
  isMobileRef.value = mediaQuery.matches

  handler = (e) => {
    isMobileRef.value = e.matches
  }

  mediaQuery.addEventListener("change", handler)
})

onUnmounted(() => {
  if (mediaQuery && handler) {
    mediaQuery.removeEventListener("change", handler)
  }
})

const isMobile = computed(() => isMobileRef.value)

// Section: Breadcrumbs structured data (SEO)
// Mounted independently here instead of inside the page structured data (useStructuredData),
// so the breadcrumb schema lives in its own <script> and is easier to maintain.
const { mountBreadcrumbStructuredData, removeStructuredDataScript } = useBreadcrumbStructuredData()
const breadcrumbStructuredDataScript = ref<HTMLScriptElement | null>(null)

const refreshBreadcrumbStructuredData = () => {
    removeStructuredDataScript(breadcrumbStructuredDataScript.value)
    breadcrumbStructuredDataScript.value = mountBreadcrumbStructuredData(props.breadcrumbs)
}

onMounted(() => {
    refreshBreadcrumbStructuredData()
})

watch(() => props.breadcrumbs, () => {
    refreshBreadcrumbStructuredData()
}, { deep: true })

onUnmounted(() => {
    removeStructuredDataScript(breadcrumbStructuredDataScript.value)
})

const _breadcrumbPopover = ref()
</script>

<template>
    <nav
        ref="scrollerRef"
        class="isolate z-10 scrollbar-hide relative md:overflow-y-hidden flex items-center text-xs md:text-sm transition-all overflow-x-hidden m-2"
        aria-label="Breadcrumb"
    >
        <!-- Breadcrumb -->
        <TransitionGroup name="list-to-down" tag="ol" class="w-full mx-auto flex">
            <li v-for="(breadcrumb, breadcrumbIdx) in breadcrumbs" :key="breadcrumbIdx"
                class="hidden first:flex last:flex md:flex items-center"
                :class="breadcrumbIdx === 0 ? 'sticky left-0 xbg-white z-10 pr-2' : ''">
                <div class="flex items-center">
                    <!-- Shorter Breadcrumb on Mobile size -->
                    <div v-if="breadcrumbs.length > 2 && breadcrumbIdx != 0" class="md:hidden flex items-center">
                        <FontAwesomeIcon v-if="breadcrumbIdx !== 0" class="flex-shrink-0 h-3 w-3 mx-3 opacity-50"
                            icon="fa-regular fa-chevron-right" aria-hidden="true" />
                        <span> {{
                            breadcrumbs[breadcrumbs.length - 2].type === 'simple'
                                ? breadcrumbs[breadcrumbs.length - 2].simple.label || breadcrumbs[breadcrumbs.length -
                                    2].simple.label
                            : breadcrumbs[breadcrumbs.length - 2].simple?.label
                            }}</span>
                    </div> 
                    <template v-if="breadcrumb.type === 'simple'">
                        <FontAwesomeIcon v-if="breadcrumbIdx !== 0" class="flex-shrink-0 h-3 w-3 mx-3 opacity-50" icon="fa-regular fa-chevron-right" aria-hidden="true" />
                        <component
                            :is="breadcrumb.simple.url || breadcrumb.simple.route?.name ? Link : 'span'"
                            :href="breadcrumb.simple.url ? breadcrumb.simple.url : breadcrumb.simple?.route?.name ? route( breadcrumb.simple.route.name, breadcrumb.simple.route.parameters ) : '#' "
                            :aria-label="breadcrumb.simple.label || breadcrumb.simple.route?.name || ctrans('Breadcrumb link :idxBreadcrumb', { idxBreadcrumb: breadcrumbIdx})"
                            class="hover:text-gray-700 overflow-hidden flex items-center"
                        >
                            <Transition name="spin-to-down">
                                <FontAwesomeIcon v-if="breadcrumb.simple?.icon" :class="breadcrumb.simple.label ? 'mr-1' : ''" fixed-width class="flex-shrink-0 h-3.5 w-3.5" :icon="breadcrumb.simple.icon" aria-hidden="true" />
                            </Transition>
        
                            <Transition name="spin-to-down">
                                <div v-if="breadcrumb.simple.label" :key="breadcrumb.simple.label" class="inline-block truncate py-1 md:py-0 max-w-[50vw] md:max-w-none">{{ breadcrumb.simple.label }}</div>
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
                                <span>{{ breadcrumb.modelWithIndex.index.label }}</span>
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
        <div @click="_breadcrumbPopover?.toggle" class="z-50 md:hidden absolute w-64 h-full xbg-red-500" aria-label="Transparency clickable area for breadcrumb popup"></div>
        <Popover ref="_breadcrumbPopover">
            <div>
                <div v-for="(breadcrumb, breadcrumbIdx) in breadcrumbs" :key="breadcrumbIdx" class="">
                    <template v-if="breadcrumb.type === 'simple'">
                        <component :is="breadcrumb.simple?.url || breadcrumb.simple?.route?.name ? Link : 'span'"
                            xclass="'' || ''"
                            class="xpl-3 py-2 grid grid-flow-col items-center justify-start"
                            :href="breadcrumb.simple?.url ? breadcrumb.simple?.url : breadcrumb.simple?.route?.name ? route(breadcrumb.simple.route.name, breadcrumb.simple.route.parameters) : ''"
                            xstyle="{ paddingLeft: 12 + breadcrumbIdx * 7 + 'px' }"
                        >
                            <!-- Icon Section -->
                            <FontAwesomeIcon v-if="breadcrumb.simple.icon && breadcrumbIdx == 0" class="flex-shrink-0 h-3.5 w-3.5" :icon="breadcrumb.simple.icon" aria-hidden="true" />

                            <!-- Icon Arrow -->
                            <FontAwesomeIcon v-if="breadcrumbIdx != 0" class="flex-shrink-0 h-3.5 w-3.5 text-gray-300" icon="fa fa-arrow-from-left" aria-hidden="true" />
                            <span v-if="breadcrumbIdx == 0 && !breadcrumb.simple.label" class="grid grid-flow-cols justify-center font-bold ml-2">
                                {{ ctrans("Storefront") }}
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
                                xstyle="{ paddingLeft: 12 + breadcrumbIdx * 7 + 'px' }"
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
                                xstyle="{ paddingLeft: 12 + (breadcrumbIdx + 1) * 7 + 'px', }"
                            >
                                <FontAwesomeIcon class="flex-shrink-0 h-3.5 w-3.5 mr-1 text-gray-300" icon="fa fa-arrow-from-left" aria-hidden="true" />
                                <span class="ml-4 mr-3">
                                    {{ breadcrumb.modelWithIndex.model.label }}
                                </span>
                            </component>
                        </div>
                    </template>
                </div>
            </div>
        </Popover>

        <div v-if="props.navigation?.previous || props.navigation?.next" class="shrink-0 flex justify-end items-center space-x-2 text-xs md:text-sm text-gray-700 font-semibold">
            <!-- Button: Previous -->
            <div class="flex justify-center items-center w-12 xl:w-8">
                <Link v-if="props.navigation.previous"
                    @start="() => isLoading = 'bcBack'"
                    @finish="() => isLoading = false"
                    :href="isLoading === 'bcBack' ? '' : props.navigation?.previous?.url ? props.navigation?.previous?.url : props.navigation?.previous?.route?.name ? route(props.navigation.previous?.route.name, props.navigation.previous?.route.parameters) + urlParameter : '#'"
                    class="rounded w-full flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer hover:text-indigo-500"
                    :title="props.navigation.previous?.label"
                >
                    <LoadingIcon v-if="isLoading === 'bcBack'" />
                    <FontAwesomeIcon v-else icon="fas fa-arrow-left" class="" aria-hidden="true" />
                </Link>
                <FontAwesomeIcon v-else icon="fas fa-arrow-left" class="opacity-20" aria-hidden="true" />
            </div>

            <!-- Button: Next -->
            <div class="flex justify-center items-center w-12 xl:w-8">
                <Link v-if="props.navigation.next"
                    @start="() => isLoading = 'bcNext'"
                    @finish="() => isLoading = false"
                    class="rounded w-full flex items-center justify-center opacity-70 hover:opacity-100 cursor-pointer hover:text-indigo-500"
                    :title="props.navigation.next?.label"
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
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

</style>