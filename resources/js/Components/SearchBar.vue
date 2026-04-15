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

library.add(faPallet, faReceipt, faTimes, faSearch, faShoppingCart, faUser)

const isOpen = defineModel<boolean>()

const emits = defineEmits<{
    (e: 'close', data: boolean): void
}>()

const layout = inject('layout', layoutStructure)
const isLoadingSearch = ref(false)
const searchValue = ref('')
const resultsSearch = ref<any[]>([])
const selectedTab = ref(null)
const isUsingLocalMock = true
const hoverItem = ref<any | null>(null)
const LOCAL_SEARCH_RESULTS = [
    {
        model_type: 'Prospect',
        model_id: 390614,
        model_icon: { icon: 'fal fa-user' },
        result: {
            code: { label: 'PRO390614' },
            icon: { icon: 'fal fa-user' },
            description: { label: 'Shop prospect' },
        }
    },
    {
        model_type: 'Order',
        model_id: 1299594,
        model_icon: null,
        result: {
            code: { label: 'DES015173' },
            icon: { icon: 'fal fa-shopping-cart' },
            meta: [
                { label: 'In Basket', tooltip: 'State' },
                { label: 'creating', tooltip: 'Status' },
                { type: 'date', label: '2026-04-13T10:32:49.000000Z', tooltip: 'Date' },
                { code: 'EUR', type: 'currency', label: 'Payment', amount: '0.00', tooltip: 'Payment' },
                { code: 'EUR', type: 'currency', label: 'Net', amount: '0.00', tooltip: 'Net' }
            ],
            route: {
                name: 'grp.org.shops.show.ordering.orders.show',
                parameters: { shop: 'dse', order: 'des015173', organisation: 'es' }
            },
            description: { label: 'Sol Virtual Shop' }
        }
    },
    {
        model_type: 'Order',
        model_id: 1299600,
        model_icon: null,
        result: {
            code: { label: 'DES015175' },
            icon: { icon: 'fal fa-shopping-cart' },
            meta: [
                { label: 'In Basket', tooltip: 'State' },
                { label: 'creating', tooltip: 'Status' },
                { type: 'date', label: '2026-04-13T10:39:22.000000Z', tooltip: 'Date' },
                { code: 'EUR', type: 'currency', label: 'Payment', amount: '0.00', tooltip: 'Payment' },
                { code: 'EUR', type: 'currency', label: 'Net', amount: '2.90', tooltip: 'Net' }
            ],
            route: {
                name: 'grp.org.shops.show.ordering.orders.show',
                parameters: { shop: 'dse', order: 'des015175', organisation: 'es' }
            },
            description: { label: 'Sol Virtual Shop' }
        }
    },
    {
        model_type: 'Order',
        model_id: 1299607,
        model_icon: null,
        result: {
            code: { label: 'DES015176' },
            icon: { icon: 'fal fa-shopping-cart' },
            meta: [
                { label: 'In Basket', tooltip: 'State' },
                { label: 'creating', tooltip: 'Status' },
                { type: 'date', label: '2026-04-13T10:47:30.000000Z', tooltip: 'Date' },
                { code: 'EUR', type: 'currency', label: 'Payment', amount: '0.00', tooltip: 'Payment' },
                { code: 'EUR', type: 'currency', label: 'Net', amount: '0.00', tooltip: 'Net' }
            ],
            route: {
                name: 'grp.org.shops.show.ordering.orders.show',
                parameters: { shop: 'dse', order: 'des015176', organisation: 'es' }
            },
            description: { label: 'Sol Virtual Shop' }
        }
    },
    {
        model_type: 'Customer',
        model_id: 716038,
        model_icon: { icon: 'fal fa-user' },
        result: {
            code: { label: 'AEU000255', tooltip: 'Reference' },
            icon: { icon: 'fal fa-user', model: 'customer' },
            meta: [
                { key: 'created_date', type: 'date', label: '2026-04-14T07:48:32.000000Z', tooltip: 'Created at' },
                { key: 'address', type: 'address', label: ['IT', 'Italy', 'Cassano Magnago'], tooltip: 'Location' },
                { key: 'contact_name', label: 'Alessia', tooltip: 'Contact name' },
                { key: 'email', label: 'mystiqueshop.it@gmail.com', tooltip: 'Email' },
                { key: 'phone', label: '+399921286717', tooltip: 'Phone' }
            ],
            route: {
                name: 'grp.org.shops.show.crm.customers.show',
                parameters: ['es', 'aeu', 'aeu000255-aeu']
            },
            container: { label: 'AW Artisan Europe' },
            state_icon: { icon: 'fas fa-circle', class: 'text-emerald-500', color: 'emerald', tooltip: 'Active' },
            description: { label: 'Mystique Shop' }
        }
    },
    {
        model_type: 'Order',
        model_id: 1292078,
        model_icon: null,
        result: {
            code: { label: 'AWS35773' },
            icon: { icon: 'fal fa-shopping-cart' },
            meta: [
                { label: 'Handling', tooltip: 'State' },
                { label: 'creating', tooltip: 'Status' },
                { type: 'date', label: '2026-03-23T15:31:55.000000Z', tooltip: 'Date' },
                { code: 'EUR', type: 'currency', label: 'Payment', amount: '1124.71', tooltip: 'Payment' },
                { code: 'EUR', type: 'currency', label: 'Net', amount: '929.51', tooltip: 'Net' }
            ],
            route: {
                name: 'grp.org.shops.show.ordering.orders.show',
                parameters: { shop: 'es', order: 'aws35773', organisation: 'es' }
            },
            description: { label: 'MARIA ANGELES LOZANO MATIAS - THE BUBBLE SHOP' }
        }
    },
    {
        model_type: 'Order',
        model_id: 1300198,
        model_icon: null,
        result: {
            code: { label: 'AWS36170' },
            icon: { icon: 'fal fa-shopping-cart' },
            meta: [
                { label: 'In Basket', tooltip: 'State' },
                { label: 'creating', tooltip: 'Status' },
                { type: 'date', label: '2026-04-14T14:35:48.000000Z', tooltip: 'Date' },
                { code: 'EUR', type: 'currency', label: 'Payment', amount: '0.00', tooltip: 'Payment' },
                { code: 'EUR', type: 'currency', label: 'Net', amount: '974.19', tooltip: 'Net' }
            ],
            route: {
                name: 'grp.org.shops.show.ordering.orders.show',
                parameters: { shop: 'es', order: 'aws36170', organisation: 'es' }
            },
            description: { label: 'MARIA ANGELES LOZANO MATIAS - THE BUBBLE SHOP' }
        }
    }
]

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

const getSearchableText = (item: any) => {
    return [
        item.model_type,
        item.result?.code?.label,
        item.result?.description?.label,
        item.result?.container?.label,
        ...(item.result?.meta || []).map((meta: any) =>
            Array.isArray(meta?.label) ? meta.label.join(' ') : meta?.label
        )
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
}

const getLocalResults = (query: string) => {
    const normalizedQuery = query.trim().toLowerCase()
    return LOCAL_SEARCH_RESULTS.filter((item) => getSearchableText(item).includes(normalizedQuery))
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
    if (query !== '') {
        resultsSearch.value = []
        isLoadingSearch.value = true
        if (isUsingLocalMock) {
            resultsSearch.value = getLocalResults(query)
            isLoadingSearch.value = false
            selectedTab.value = null
            return
        }

        await fetch(`${urlSearch()}?q=${query}&route_src=${route().current()}${paramsToString()}`)
            .then(response => {
                response.json().then((data: { data: {} }) => {
                    resultsSearch.value = data.data
                    console.log('query:', query, resultsSearch.value)
                    isLoadingSearch.value = false
                    selectedTab.value = null
                })
            })
            .catch(() => {
                resultsSearch.value = getLocalResults(query)
                isLoadingSearch.value = false
                selectedTab.value = null
            })
    }
}, 700)
const isOnTyping = ref(false)
const onTypeSearch = () => {
    // searchValue.value = e.target.value
    isOnTyping.value = true
    console.log("searfh", searchValue.value)
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
    if (hoverItem.value) return hoverItem.value

    if (selectedType.value) {
        return resultsSearch.value.find(r => r.model_type === selectedType.value)
    }

    return resultsSearch.value[0]
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
    <TransitionRoot :show="isOpen" as="template" @after-leave="() => (searchValue = '', resultsSearch = [])" appear>
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
                        <div class="border-b p-3 flex items-center gap-3">
                            <FontAwesomeIcon icon="fa-regular fa-search" class="text-gray-400" />
                            <input v-model="searchValue" @input="onTypeSearch" type="text"
                                class="h-12 w-full border-0 bg-transparent text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm" placeholder="Search..." />
                            <button @click="closeModal">✕</button>
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

                            <!-- LEFT -->
                            <div class="col-span-3 border-r p-4 bg-gray-50">
                                <p class="text-xs text-gray-400 mb-2">Query</p>
                                <p class="font-semibold mb-4">{{ searchValue }}</p>

                                <p class="text-xs text-gray-400 mb-2">Types</p>

                                <button
                                    class="w-full rounded-xl px-3 py-2.5 text-left text-sm font-medium transition"
                                    :class="!selectedType ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                                    @click="selectedType = null"
                                >
                                    All ({{ resultsSearch.length ?? 0 }})
                                </button>

                                <button
                                    v-for="(count, type) in groupedTypes"
                                    :key="type"
                                    @click="selectedType = type"
                                    class="mt-1 w-full rounded-xl px-3 py-2.5 text-left text-sm font-medium transition"
                                    :class="selectedType === type ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
                                >
                                    {{ type }} ({{ count }})
                                </button>
                            </div>

                            <!-- MIDDLE (PREVIEW) -->
                            <component
                                :is="highlightItem && hasRoute(highlightItem) ? Link : 'div'"
                                :href="highlightItem && hasRoute(highlightItem)
                                    ? route(highlightItem.result.route.name, highlightItem.result.route.parameters)
                                    : undefined"
                                class="col-span-4 border-r flex flex-col items-center justify-center text-center transition"
                                :class="highlightItem && hasRoute(highlightItem)
                                    ? 'cursor-pointer hover:bg-slate-100 hover:border-slate-200 hover:shadow-sm active:scale-[0.99]'
                                    : ''"
                                @click="highlightItem && hasRoute(highlightItem) ? closeModal() : undefined"
                            >

                                <!-- TITLE -->
                                <p class="text-sm text-gray-400 mb-3">Preview</p>

                                <!-- CONTENT -->
                                <template v-if="highlightItem">
                                    <p class="text-lg font-semibold text-slate-900">
                                        {{ highlightItem.result?.description?.label || highlightItem.result?.code?.label }}
                                    </p>

                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ highlightItem.result?.code?.label }}
                                    </p>

                                    <p
                                        v-if="hasRoute(highlightItem)"
                                        class="mt-4 text-xs font-medium uppercase tracking-wide text-slate-500"
                                    >
                                        {{ trans('Click') }}
                                    </p>
                                </template>

                                <!-- EMPTY -->
                                <template v-else>
                                    <div class="text-gray-400">
                                        No preview
                                    </div>
                                </template>

                            </component>
                            <!-- RIGHT -->
                            <div class="col-span-5 overflow-y-auto p-4 space-y-2">

                                <div v-if="isLoadingSearch" class="text-gray-400">
                                    Searching...
                                </div>

                                <div v-for="item in filteredResults" :key="item.model_id"
                                    class="rounded-xl border border-transparent p-3 transition"
                                    :class="hasRoute(item) ? 'cursor-pointer hover:border-slate-200 hover:bg-gray-100' : 'hover:bg-gray-100'"
                                    @mouseenter="hoverItem = item">
                                    <SearchResultDefault :data="item.result" :modelType="item.model_type"
                                        @finishVisit="closeModal" />
                                </div>

                                <div v-if="!filteredResults.length && !isLoadingSearch" class="text-gray-400">
                                    No results
                                </div>

                            </div>

                        </div>

                    </DialogPanel>
                </TransitionChild>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
