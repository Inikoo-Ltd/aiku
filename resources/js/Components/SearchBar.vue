<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Mon, 06 Mar 2023 13:45:35 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { inject, ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash-es'
import SearchResultDefault from '@/Components/Search/SearchResultDefault.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { faPallet, faReceipt, faTimes, faSearch, faShoppingCart, faUser } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import Skeleton from 'primevue/skeleton';

library.add(faPallet, faReceipt, faTimes, faSearch, faShoppingCart, faUser)

type User = {
    id: number
    username: string
    email: string
    contact_name: string
    status: boolean
}

// Guest dari API
type Guest = {
    id: number
    slug: string
    code: string
    contact_name: string
    email: string
}

type SearchResults = {
    users: User[]
    guests: Guest[]
}

const isOpen = defineModel<boolean>()

const emits = defineEmits<{
    (e: 'close', data: boolean): void
}>()

const layout = inject('layout', layoutStructure)
const isLoadingSearch = ref(false)
const searchValue = ref('')
const resultsSearch = ref<SearchResults | null>(null)
let abortController: AbortController | null = null

const selectedTab = ref(null)
const isUsingLocalMock = false
const hoverItem = ref<any | null>(null)

// Method: parameter to string '&organisation=aw&fulfilment=idf'
const paramsToString = () => {
    return route().routeParams ? '&' + Object.entries(route().routeParams).map(([key, value]) => `${key}=${value}`).join('&') : ''
}


// Method: Fetch result
const urlSearch = () => {
    return layout.app.name == 'retina'
        ? `${location.origin}/app/search`
        : `${location.origin}/search`
}

const groupedTypes = computed(() => {
    const map: Record<string, number> = {}

     if (!resultsSearch.value?.length) return map

    resultsSearch.value.forEach(item => {
        map[item.model_type] = (map[item.model_type] || 0) + 1
    })

    return map
})

const selectedType = ref<string | null>(null)
const filteredResults = computed(() => {
    if (!selectedType.value) return resultsSearch.value
    return resultsSearch.value.filter(r => r.model_type === selectedType.value)
})
const fetchApi = debounce(async (query: string) => {
    isOnTyping.value = false

    if (!query) return

    abortController?.abort()
    abortController = new AbortController()

    resultsSearch.value = null
    isLoadingSearch.value = true

    try {
        const url = `${urlSearch()}?q=${query}&route_src=${route().current()}${paramsToString()}`
        const response = await fetch(url, { signal: abortController.signal })
        const data = await response.json()
        resultsSearch.value = data.results
    } catch (e) {
        if ((e as DOMException).name === 'AbortError') return
        resultsSearch.value = null
        selectedTab.value = null
    } finally {
        isLoadingSearch.value = false
    }
}, 400)
const isOnTyping = ref(false)
const onTypeSearch = () => {
    // searchValue.value = e.target.value
    isOnTyping.value = true
    fetchApi(searchValue.value)
}

function countModelTypes(data) {
    // Initialize an empty object to store counts
    const counts = {}

    // Iterate over the array
    data.forEach(item => {
        // Get the model_type from each item
        const modelType = item.model_type

        // If the model_type exists in the counts object, increment its count
        if (counts[modelType]) {
            counts[modelType]++
        } else {
            // If the model_type doesn't exist, initialize its count to 1
            counts[modelType] = 1
        }
    })

    // Return the counts object
    return counts
}

const highlightItem = computed(() => {
    return hoverItem.value
})

const closeModal = () => {
    isOpen.value = false
    searchValue.value = ''
    resultsSearch.value = []
    selectedTab.value = null
}

const hasRoute = (item: any) => {
    return Boolean(item?.result?.route?.name)
}
</script>

<template>
    <TransitionRoot :show="isOpen" as="template" @after-leave="() => (searchValue = '', resultsSearch = null)" appear>
        <Dialog as="div" class="relative z-50" @close="closeModal">

            <!-- Overlay -->
            <TransitionChild enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" />
            </TransitionChild>

            <!-- Modal -->
            <div class="fixed inset-0 flex items-start justify-center pt-16 px-4">
                <TransitionChild enter="ease-out duration-200" enter-from="opacity-0 scale-95"
                    enter-to="opacity-100 scale-100" leave="ease-in duration-150" leave-from="opacity-100 scale-100"
                    leave-to="opacity-0 scale-95">
                    <DialogPanel
                        class="w-full max-w-lg sm:max-w-2xl md:min-w-[60vw] lg:max-w-4xl xl:max-w-[1200px] h-[75vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">

                        <!-- HEADER -->
                        <div class="border-b p-3 flex items-center">
                            <FontAwesomeIcon icon="fa-regular fa-search" class="text-gray-400" />
                            <input v-model="searchValue" @input="onTypeSearch" type="text"
                                class="h-12 w-full border-0 bg-transparent text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm" placeholder="Search..." />
                            <button @click="closeModal"><FontAwesomeIcon icon="fal fa-times" class="text-lg" /></button>
                        </div>
                        
                        <!-- EMPTY -->
                        <div v-if="!searchValue" class="flex flex-1 items-center justify-center text-center text-gray-400 p-6">
                            <div class="space-y-2">
                                <p class="text-base font-medium text-gray-500">{{ trans('Type to search...') }}</p>
                                <p class="text-sm">{{ trans('Search across orders, customers, prospects and more') }}</p>
                            </div>
                        </div>

                        <!-- CONTENT -->
                        <div v-else class="grid grid-cols-12 flex-1 min-h-0">
                           <div class="col-span-3 border-r p-4 bg-gray-50">
                                <p class="text-xs text-gray-400 mb-2">Query</p>
                                <p class="font-semibold mb-4">{{ searchValue }}</p>

                                <p class="text-xs text-gray-400 mb-2">Summary</p>
                                <div v-if="isLoadingSearch" class="space-y-2">
                                    <Skeleton height="2.5rem" borderRadius="0.75rem" />
                                    <Skeleton height="2.5rem" borderRadius="0.75rem" />
                                </div>  
                                <div class="space-y-2" v-else>
                                    <div
                                    class="p-3 rounded-xl bg-white text-sm flex items-center justify-between 
                                            cursor-pointer transition hover:bg-slate-100 hover:shadow-sm active:scale-[0.98]"
                                    >
                                        <span class="font-medium text-slate-700">Users</span>
                                        <span class="text-xs text-gray-400">
                                            {{ resultsSearch?.users?.length ?? 0 }}
                                        </span>
                                    </div>

                                    <div
                                    class="p-3 rounded-xl bg-white text-sm flex items-center justify-between 
                                            cursor-pointer transition hover:bg-slate-100 hover:shadow-sm active:scale-[0.98]"
                                    >
                                        <span class="font-medium text-slate-700">Guests</span>
                                        <span class="text-xs text-gray-400">
                                            {{ resultsSearch?.guests?.length ?? 0 }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-span-4 border-r flex flex-col min-h-0">
                                <!-- CONTENT -->
                                <div class="flex-1 p-4 space-y-4 overflow-y-auto">

                                    <div v-if="isLoadingSearch" class="space-y-4">
                                        <div v-for="i in 5" :key="i"
                                            class="p-4 rounded-xl border bg-white">
                                            
                                            <div class="flex justify-between items-center mb-2">
                                                <Skeleton width="60%" height="1rem" />
                                                <Skeleton width="40px" height="0.75rem" borderRadius="999px" />
                                            </div>

                                            <Skeleton width="80%" height="0.75rem" class="mb-2" />
                                            <Skeleton width="40%" height="0.75rem" />
                                        </div>
                                    </div>
                                    <div v-else-if="resultsSearch?.users?.length">
                                        <div
                                        v-for="user in resultsSearch.users"
                                        :key="user.id"
                                        class="group p-4 rounded-xl border border-transparent bg-slate-50 
                                                hover:border-slate-200 hover:bg-slate-150 hover:shadow-sm 
                                                cursor-pointer transition-all duration-150 mb-3"
                                        >
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-semibold text-slate-900">
                                                    {{ user.contact_name }}
                                                </p>

                                                <span
                                                class="text-[10px] px-2 py-0.5 rounded-full"
                                                :class="user.status ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500'"
                                                >
                                                    {{ user.status ? 'Active' : 'Inactive' }}
                                                </span>
                                            </div>

                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ user.email }}
                                            </p>

                                            <p class="text-xs text-gray-400 mt-2">
                                                @{{ user.username }}
                                            </p>
                                        </div>
                                    </div>

                                    <div v-else class="flex-1 flex items-center justify-center text-gray-400 text-sm">
                                        No data
                                    </div>

                                </div>
                            </div>
                            <!-- RIGHT -->
                           <div class="col-span-5 flex flex-col min-h-0">
                                <!-- CONTENT -->
                                <div class="flex-1 p-4 space-y-4 overflow-y-auto">
                                    <div v-if="isLoadingSearch" class="space-y-4">
                                        <div v-for="i in 5" :key="i"
                                            class="p-4 rounded-xl border bg-slate-50">

                                            <Skeleton width="70%" height="1rem" class="mb-2" />
                                            <Skeleton width="80%" height="0.75rem" class="mb-2" />
                                            <Skeleton width="40%" height="0.75rem" />
                                        </div>
                                    </div>
                                    <div v-else-if="resultsSearch?.guests?.length">
                                       <div
                                        v-for="guest in resultsSearch.guests"
                                        :key="guest.id"
                                        class="group p-4 rounded-xl border border-transparent bg-slate-50 
                                                hover:border-slate-200 hover:bg-slate-150 hover:shadow-sm 
                                                cursor-pointer transition-all duration-150 mb-3"
                                        >
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-semibold text-slate-900">
                                                    {{ guest.contact_name }}
                                                </p>
                                            </div>

                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ guest.email }}
                                            </p>

                                            <p class="text-xs text-gray-400 mt-2">
                                                Code: {{ guest.code }}
                                            </p>
                                        </div>
                                    </div>

                                     <div v-else class="flex-1 flex items-center justify-center text-gray-400 text-sm">
                                        No data
                                    </div>

                                </div>
                            </div>

                        </div>

                    </DialogPanel>
                </TransitionChild>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
