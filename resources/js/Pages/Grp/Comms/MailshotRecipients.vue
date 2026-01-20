<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3' // Pastikan import router
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { ref, watch, computed } from 'vue'
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faFilter, faTimes, faPlus } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { debounce } from 'lodash'

// Import Datepicker
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'

library.add(faChevronDown, faFilter, faTimes, faPlus)

const props = defineProps<{
    title: string
    pageHead: any
    mailshot: any
    filtersStructure: Record<string, any>
    filters: any
    customers: any
}>()

const activeFilters = ref<Record<string, any>>({ ...props.filters })

const availableFilters = computed(() => {
    const list = []
    Object.values(props.filtersStructure).forEach(group => {
        Object.entries(group.filters).forEach(([key, filter]) => {
            if (!activeFilters.value[key]) {
                list.push({ key, ...filter })
            }
        })
    })
    return list
})

const addFilter = (filterKey: string, filterConfig: any) => {
    let value: any = true

    if (filterKey === 'orders_in_basket') {
        value = {
            value: true,
            date_range: null,
            amount_range: { min: null, max: null }
        }
    } else if (filterConfig.type === 'daterange') {
        value = { date_range: null }
    } else if (filterConfig.type === 'boolean' && filterConfig.options && filterConfig.options.date_range) {
         value = { value: true, date_range: null }
    } else if (filterConfig.type === 'select') {
        if (filterConfig.options && filterConfig.options.length > 0) {
            value = filterConfig.options[0].value
        } else {
            value = null
        }
    } else if (filterConfig.type === 'multiselect') {
        value = []
    }

    activeFilters.value[filterKey] = {
        value: value,
        config: filterConfig
    }
}

const setDatePreset = (filter: any, days: number | string) => {
    const end = new Date();
    const start = new Date();

    if (days === 'custom') {
        return;
    }

    if (typeof days === 'number') {
        start.setDate(end.getDate() - days);
        filter.value.date_range = [start, end];
    }
}

const onPresetChange = (filter: any, event: Event) => {
    const target = event.target as HTMLSelectElement;
    const value = target.value;


    filter._ui_preset = value;

    if (value === 'custom') {

        if (!filter.value.date_range) {
             filter.value.date_range = null;
        }
    } else {
        const days = parseInt(value);
        if (!isNaN(days)) {
            const end = new Date();
            const start = new Date();
            start.setDate(end.getDate() - days);
            filter.value.date_range = [start, end];
        }
    }
}

const removeFilter = (key: string) => {
    delete activeFilters.value[key]
}

const clearAllFilters = () => {
    activeFilters.value = {}
}

const fetchFilteredRecipients = debounce(() => {
    const filtersPayload = {}

    Object.keys(activeFilters.value).forEach(key => {
        const filter = activeFilters.value[key]

        filtersPayload[key] = {
            value: filter.value
        }
    })

    router.get(
        route(route().current(), route().params),
        {
            filters: filtersPayload
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['customers', 'filters']
        }
    )
}, 500)

watch(activeFilters, () => {
    fetchFilteredRecipients()
}, { deep: true })

</script>

<template>
    <Head :title="title" />

    <PageHeading :data="pageHead" />

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">

        <div class="flex items-center space-x-4 mb-6">
            <Menu as="div" class="relative inline-block text-left">
                <MenuButton as="template">
                    <Button type="white-w-outline" icon="fas fa-filter" label="Filter">
                        <template #iconRight>
                            <span v-if="Object.keys(activeFilters).length > 0"
                                  class="ml-2 bg-gray-200 text-gray-700 py-0.5 px-2 rounded-full text-xs">
                                {{ Object.keys(activeFilters).length }}
                            </span>
                        </template>
                    </Button>
                </MenuButton>

                <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                    <MenuItems class="absolute left-0 z-10 mt-2 w-56 origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                        <div class="py-1">
                            <MenuItem v-for="filter in availableFilters" :key="filter.key" v-slot="{ active }">
                                <button @click="addFilter(filter.key, filter)"
                                        :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block w-full px-4 py-2 text-left text-sm']">
                                    {{ filter.label }}
                                </button>
                            </MenuItem>
                            <div v-if="availableFilters.length === 0" class="px-4 py-2 text-sm text-gray-500">
                                No more filters available
                            </div>
                        </div>
                    </MenuItems>
                </transition>
            </Menu>

             <!-- Clear Filters -->
            <button v-if="Object.keys(activeFilters).length > 0"
                    @click="clearAllFilters"
                    class="text-sm text-gray-600 hover:text-gray-900">
                Clear filters
            </button>
        </div>

        <!-- Active Filters Area -->
        <div class="bg-white shadow rounded-lg mb-8" v-if="Object.keys(activeFilters).length > 0">
             <div class="px-4 py-5 sm:p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="(filter, key) in activeFilters" :key="key" class="relative border border-gray-200 rounded-md p-4 bg-gray-50">

                    <div class="flex justify-between items-start mb-2">
                        <label class="block text-sm font-medium text-gray-700">{{ filter.config.label }}</label>
                        <button @click="removeFilter(key as string)" class="text-gray-400 hover:text-red-500">
                            <FontAwesomeIcon icon="fas fa-times" />
                        </button>
                    </div>

                    <div class="mt-2">
                        <!-- Boolean Filter -->
                        <div v-if="filter.config.type === 'boolean'" class="space-y-3">
                            <span
                                class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                Active
                            </span>

                            <div v-if="filter.config.options && filter.config.options.date_range">
                                <label class="block text-xs font-medium text-gray-500 mb-1">
                                    {{ filter.config.options.date_range.label }}
                                </label>

                                <div v-if="key === 'orders_in_basket'">
                                    <select
                                        class="block w-full rounded-md border-0 py-1.5 mb-2 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-xs sm:leading-6"
                                        @change="onPresetChange(filter, $event)"
                                    >
                                        <option value="" disabled selected>Select time frame</option>
                                        <option :value="3">1-3 Days ago</option>
                                        <option :value="7">Last 7 Days</option>
                                        <option :value="14">Last 14 Days</option>
                                        <option value="custom">Custom Range</option>
                                    </select>

                                    <div v-if="filter._ui_preset === 'custom' || (filter.value.date_range && !filter._ui_preset)">
                                         <VueDatePicker
                                            v-model="filter.value.date_range"
                                            range
                                            :enable-time-picker="false"
                                            :placeholder="filter.config.options.date_range.placeholder || 'Select range'"
                                            auto-apply
                                            :format="'yyyy-MM-dd'"
                                        />
                                    </div>
                                </div>

                                <div v-else>
                                    <VueDatePicker
                                        v-model="filter.value.date_range"
                                        range
                                        :enable-time-picker="false"
                                        :placeholder="filter.config.options.date_range.placeholder || 'Select range'"
                                        auto-apply
                                        :format="'yyyy-MM-dd'"
                                    />
                                </div>
                            </div>
                        </div>

                         <div v-else-if="filter.config.type === 'select'">
                             <select v-model="filter.value"
                                     class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                <option :value="null" disabled>Select {{ filter.config.label }}</option>
                                <option v-for="opt in filter.config.options" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                             </select>
                        </div>



                    </div>
                </div>
             </div>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-base font-semibold leading-6 text-gray-900">Targeted Customers</h3>
                <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                    Total: {{ customers.total }}
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Name</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Email</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Joined</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="customer in customers.data" :key="customer.id">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                {{ customer.contact_name }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ customer.email }}</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                {{ new Date(customer.created_at).toLocaleDateString() }}
                            </td>
                        </tr>
                        <tr v-if="customers.data.length === 0">
                             <td colspan="3" class="py-10 text-center text-sm text-gray-500">
                                No customers found.
                             </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Links (Simple) -->
            <div v-if="customers.links && customers.links.length > 3" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between sm:px-6">
                 <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <component
                                :is="link.url ? 'a' : 'span'"
                                v-for="(link, i) in customers.links"
                                :key="i"
                                :href="link.url"
                                @click.prevent="link.url && router.visit(link.url, { preserveState: true, preserveScroll: true, only: ['customers'] })"
                                v-html="link.label"
                                :class="[
                                    link.active ? 'bg-indigo-600 text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0',
                                    'relative inline-flex items-center px-4 py-2 text-sm font-semibold focus:z-20'
                                ]"
                            />
                        </nav>
                    </div>
                 </div>
            </div>
        </div>

    </div>
</template>
