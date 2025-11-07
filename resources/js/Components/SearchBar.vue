<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Mon, 06 Mar 2023 13:45:35 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { inject, ref } from 'vue'
import { Combobox, ComboboxOptions, ComboboxOption, Dialog, DialogPanel, TransitionChild, TransitionRoot, } from '@headlessui/vue'
import { Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash-es'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import SearchResultDefault from '@/Components/Search/SearchResultDefault.vue'
import SearchResultPallet from '@/Components/Search/SearchResultPallet.vue'
import SearchResultCustomer from '@/Components/Search/SearchResultCustomer.vue'
import SearchResultFulfilmentCustomer from '@/Components/Search/SearchResultFulfilmentCustomer.vue'
import SearchResult from '@/Components/Search/SearchResult.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'


import { faPallet, faReceipt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import Icon from '@/Components/Icon.vue'
library.add(faPallet, faReceipt)

const isOpen = defineModel<boolean>()

const emits = defineEmits<{
    (e: 'close', data: boolean): void
}>()

const layout = inject('layout', layoutStructure)
const isLoadingSearch = ref(false)
const searchValue = ref('')
const resultsSearch = ref()
const selectedTab = ref(null)

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
const fetchApi = debounce(async (query: string) => {
    isOnTyping.value = false
    if (query !== '') {
        resultsSearch.value = null
        isLoadingSearch.value = true
        await fetch(`${urlSearch()}?q=${query}&route_src=${route().current()}${paramsToString()}`)
            .then(response => {
                response.json().then((data: { data: {} }) => {
                    resultsSearch.value = data.data
                    console.log('query:', query, resultsSearch.value)
                    isLoadingSearch.value = false
                    selectedTab.value = null
                })
            })
            .catch(err => console.log(err))
    }
}, 700)
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
</script>

<template>
    <TransitionRoot :show="isOpen" as="template" @after-leave="() => (searchValue = '', resultsSearch = [])" appear>
        <Dialog as="div" class="relative z-[21]" @close="() => isOpen = false">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-slate-700/25" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 pt-20 px-12">
                <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0 scale-95"
                    enter-to="opacity-100 scale-100" leave="ease-in duration-200" leave-from="opacity-100 scale-100"
                    leave-to="opacity-0 scale-95">
                    <DialogPanel class="bg-white shadow-2xl mx-auto max-w-3xl h-[80vh] overflow-y-auto transform overflow-hidden rounded-xl ring-1 ring-black/5 transition-all">
                        <!-- Section: Search input -->
                        <div class="relative border-b border-gray-300">
                            <FontAwesomeIcon class="pointer-events-none absolute top-3.5 left-4 h-5 w-5 text-gray-400"
                                aria-hidden="true" icon="fa-regular fa-search" size="lg" />
                            <input
                                v-model="searchValue"
                                @input="() => onTypeSearch()"
                                type="text"
                                class="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm"
                                placeholder="Search..."
                            >
                        </div>

                        <!-- Section: Search is 0 data -->
                        <div v-if="!searchValue.length" class="py-4 text-center italic text-gray-400">
                            {{ trans('Nothing to show') }}
                        </div>

                        <!-- Section: Search result -->
                        <TabGroup v-else-if="searchValue">
                            <!-- Section: Tabs -->
                            <TabList v-if="isLoadingSearch || resultsSearch?.length" class="flex gap-x-2 px-4 py-2 overflow-x-auto w-full" v-slot="{ selectedIndex }">
                                <!-- Tabs: Skeleton -->
                                <div v-if="isLoadingSearch" class="flex gap-x-2">
                                    <div v-for=" of 3" class="h-10 skeleton min-w-28 w-min rounded-lg" />
                                </div>

                                <!-- Tab: Show all -->
                                <button v-else as="button"
                                    @click="() => selectedTab = null"
                                    key="All"
                                    class="min-w-28 w-min rounded py-2.5 px-2 text-sm leading-5 whitespace-nowrap ring-1 ring-slate-200 focus:ring-transparent focus:ring-offset-2 focus:ring-offset-slate-500 focus:outline-none focus:ring-2 transition-all"
                                    :class="[
                                        !selectedTab
                                            ? 'bg-indigo-600 text-white'
                                            : 'text-slate-500 hover:bg-slate-50',
                                        ]">
                                    {{ trans('Show all') }} (<span class="font-bold">{{ resultsSearch?.length || 0}}</span>)
                                </button>

                                <template v-if="!isLoadingSearch && resultsSearch">
                                    <button v-for="(tabCount, tabName, tabIdx) in countModelTypes(resultsSearch)" as="button"
                                        @click="() => selectedTab = tabName"
                                        :key="tabName+tabIdx"
                                        class="w-fit w rounded py-2.5 pl-7 pr-8 text-sm leading-5 whitespace-nowrap ring-1 ring-slate-200 focus:ring-transparent focus:ring-offset-2 focus:ring-offset-slate-500 focus:outline-none focus:ring-2 transition-all"
                                        :class="[
                                            tabName == selectedTab
                                                ? 'bg-indigo-600 text-white'
                                                : 'text-slate-500 hover:bg-slate-50',
                                            ]"
                                        >
                                        <Icon :data="resultsSearch.find(result => result.model_type === tabName).model_icon" />
                                        {{tabName}} (<span class="font-bold">{{ tabCount }}</span>)
                                    </button>
                                </template>
                            </TabList>

                            
                            <div v-else-if="isOnTyping" class="py-4 text-center text-gray-400 italic">
                                {{ trans("Waiting to finish typing") }}..
                            </div>
                            
                            <div v-else class="py-4 text-center text-gray-600">
                                No result to show for <span class="font-bold">{{ searchValue }}</span>
                            </div>

                            <!-- Section: Skeleton -->
                            <div v-if="isLoadingSearch" class="border-t-2 border-slate-300">
                                <div class="flex flex-auto flex-col justify-between gap-y-4 p-6">
                                    <div v-for=" of 3" class="flex gap-x-2 h-11 rounded overflow-hidden">
                                        <div class="skeleton h-full aspect-square rounded-md" />
                                        <div class="flex flex-col h-full w-full gap-y-1">
                                            <div class="skeleton h-2/3 max-w-56 rounded" />
                                            <div class="skeleton h-1/3 max-w-40 rounded" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section: Results -->
                            <TransitionGroup name="list" tag="ul" v-if="resultsSearch?.length" class="border-t-2 pt-4 border-slate-300">
                                <template v-for="(result, resultIdx) in resultsSearch"
                                    :key="result.model_type + result.model_id">
                                    <li v-if="selectedTab ? result.model_type === selectedTab : true"
                                        class="bg-white hover:bg-slate-100 border-l-4 border-transparent hover:border-yellow-500  pt-0 pb-0 pl-6 mr-6 transition-none ring-white/60 ring-offset-2 ring-offset-blue-400 focus:outline-none focus:ring-2 cursor-pointer"
                                    >
                                        <!-- <SearchResultPallet v-if="result.model_type == 'Pallet'" :data="result.model" /> -->
                                        <!-- <SearchResultCustomer v-else-if="result.model_type == 'Customer'" :data="result.model" />
                                        <SearchResultFulfilmentCustomer v-else-if="result.model_type == 'FulfilmentCustomer'" :data="result.model" /> -->
                                        <SearchResultDefault :data="result.result" :modelType="result.model_type" @finishVisit="() => isOpen = false" />
                                    </li>
                                </template>
                            </TransitionGroup >
                        </TabGroup>

                        
                    </DialogPanel>
                </TransitionChild>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
