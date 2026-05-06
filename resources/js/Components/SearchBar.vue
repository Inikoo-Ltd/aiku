<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Mon, 06 Mar 2023 13:45:35 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { inject, ref, computed, defineAsyncComponent } from 'vue'
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash-es'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { faTimes, faSearch } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import Skeleton from 'primevue/skeleton'

library.add(faTimes, faSearch)

const scopeComponents: Record<string, ReturnType<typeof defineAsyncComponent>> = {
    sysadmin: defineAsyncComponent(() => import('@/Components/Search/SearchResultSysAdmin.vue')),
    catalogue: defineAsyncComponent(() => import('@/Components/Search/SearchResultCatalogue.vue')),
    customers: defineAsyncComponent(() => import('@/Components/Search/SearchResultCustomers.vue')),
}

const isOpen = defineModel<boolean>()

const layout = inject('layout', layoutStructure)
const isLoadingSearch = ref(false)
const searchValue = ref('')
const scope = ref<string | null>(null)
const resultsSearch = ref<Record<string, any> | null>(null)
let abortController: AbortController | null = null

const activeComponent = computed(() => {
    return scope.value ? scopeComponents[scope.value] ?? null : null
})

const paramsToString = () => {
    return route().routeParams
        ? '&' + Object.entries(route().routeParams).map(([key, value]) => `${key}=${value}`).join('&')
        : ''
}

const urlSearch = () => {
    return layout.app.name == 'retina'
        ? `${location.origin}/app/search`
        : `${location.origin}/search`
}

const fetchApi = debounce(async (query: string) => {
    if (!query) return

    abortController?.abort()
    abortController = new AbortController()

    resultsSearch.value = null
    isLoadingSearch.value = true

    try {
        const url = `${urlSearch()}?q=${query}&route_src=${route().current()}${paramsToString()}`
        const response = await fetch(url, { signal: abortController.signal })
        const data = await response.json()
        scope.value = data.scope ?? null
        resultsSearch.value = data.results ?? null
    } catch (e) {
        if ((e as DOMException).name === 'AbortError') return
        resultsSearch.value = null
        scope.value = null
    } finally {
        isLoadingSearch.value = false
    }
}, 400)

const onTypeSearch = () => {
    fetchApi(searchValue.value)
}

const closeModal = () => {
    isOpen.value = false
    searchValue.value = ''
    resultsSearch.value = null
    scope.value = null
}
</script>

<template>
    <TransitionRoot :show="isOpen" as="template" @after-leave="() => { searchValue = ''; resultsSearch = null }" appear>
        <Dialog as="div" class="relative z-50" @close="closeModal">

            <TransitionChild enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" />
            </TransitionChild>

            <div class="fixed inset-0 flex items-start justify-center pt-16 px-4">
                <TransitionChild enter="ease-out duration-200" enter-from="opacity-0 scale-95"
                    enter-to="opacity-100 scale-100" leave="ease-in duration-150" leave-from="opacity-100 scale-100"
                    leave-to="opacity-0 scale-95">
                    <DialogPanel
                        class="w-full max-w-lg sm:max-w-2xl md:min-w-[60vw] lg:max-w-4xl xl:max-w-[1200px] h-[75vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">

                        <div class="border-b p-3 flex items-center gap-2">
                            <FontAwesomeIcon icon="fal fa-search" class="text-gray-400" />
                            <input
                                v-model="searchValue"
                                @input="onTypeSearch"
                                type="text"
                                class="h-12 w-full border-0 bg-transparent text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm"
                                :placeholder="trans('Search...')"
                            />
                            <button @click="closeModal">
                                <FontAwesomeIcon icon="fal fa-times" class="text-lg" />
                            </button>
                        </div>

                        <div v-if="!searchValue" class="flex flex-1 items-center justify-center text-center text-gray-400 p-6">
                            <div class="space-y-2">
                                <p class="text-base font-medium text-gray-500">{{ trans('Type to search...') }}</p>
                                <p class="text-sm">{{ trans('Search across orders, customers, prospects and more') }}</p>
                            </div>
                        </div>

                        <div v-else-if="!isLoadingSearch && !activeComponent" class="flex flex-1 items-center justify-center text-gray-400 p-6">
                            <p class="text-sm">{{ trans('No results found') }}</p>
                        </div>

                        <div v-else-if="isLoadingSearch && !activeComponent" class="grid grid-cols-12 flex-1 min-h-0">
                            <div class="col-span-3 border-r p-4 bg-gray-50 space-y-2">
                                <Skeleton height="2.5rem" borderRadius="0.75rem" />
                                <Skeleton height="2.5rem" borderRadius="0.75rem" />
                            </div>
                            <div class="col-span-9 p-4 space-y-4">
                                <div v-for="i in 5" :key="i" class="p-4 rounded-xl border bg-white">
                                    <Skeleton width="60%" height="1rem" class="mb-2" />
                                    <Skeleton width="80%" height="0.75rem" />
                                </div>
                            </div>
                        </div>

                        <div v-else class="grid grid-cols-12 flex-1 min-h-0">
                            <component
                                v-model:open="isOpen"
                                :is="activeComponent"
                                :results="resultsSearch"
                                :is-loading="isLoadingSearch"
                                :query="searchValue"
                            />
                        </div>

                    </DialogPanel>
                </TransitionChild>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
