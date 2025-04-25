<template>
    <nav v-if="!hasData || pagination?.total < 1 || exportLinks?.export?.route || hasPagination && meta?.total > 15"
        class="bg-white px-4 py-3 flex items-center space-x-2 justify-between border-t border-gray-200 sm:px-4">
        <p v-if="!hasData || pagination.total < 1" class="mx-auto">
            {{ trans('No result found') }}
        </p>

        <!-- Button: Download Table -->
        <slot name="tableDownload" class="">
            <!-- <TableDownload v-if="exportLinks?.export?.route" :exportLinks="exportLinks" /> -->
        </slot>

        <template v-if="hasPagination && meta?.total > 15">
            <!-- simple and mobile -->
            <div v-if="hasData" class="flex-1 flex justify-between" :class="{ 'sm:hidden': hasLinks }">
                <component :is="previousPageUrl ? 'a' : 'div'" :class="{
                    'cursor-not-allowed text-gray-400': !previousPageUrl,
                    'text-gray-700 hover:text-gray-500': previousPageUrl
                }" :href="previousPageUrl" :dusk="previousPageUrl ? 'pagination-simple-previous' : null"
                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md bg-white"
                    @click.prevent="onClick(previousPageUrl)">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16l-4-4m0 0l4-4m-4 4h18" />
                    </svg>
                    <span class="hidden sm:inline ml-2">{{ translations.previous }}</span>
                </component>
                <PerPageSelector v-if="perPageOptions?.length" dusk="per-page-mobile" :value="perPage" :options="perPageOptions"
                    :on-change="onPerPageChange" />
                <component :is="nextPageUrl ? 'a' : 'div'" :class="{
                    'cursor-not-allowed text-gray-400': !nextPageUrl,
                    'text-gray-700 hover:text-gray-500': nextPageUrl
                }" :href="nextPageUrl" :dusk="nextPageUrl ? 'pagination-simple-next' : null"
                    class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md bg-white"
                    @click.prevent="onClick(nextPageUrl)">
                    <span class="hidden sm:inline mr-2">{{ translations.next }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </component>
            </div>
            <!-- Full pagination -->
            <div v-if="hasData && hasLinks" class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div class="flex flex-row space-x-4 items-center flex-grow">
                    <!-- Button row per page -->
                    <PerPageSelector v-if="perPageOptions?.length && pagination.total > 15" dusk="per-page-full" :value="perPage"
                        :options="perPageOptions" :on-change="onPerPageChange" />
                    <!-- Counts per page -->
                    <p v-if="pagination.total > 15" class="hidden md:block text-sm text-gray-700 flex-grow">
                        <span class="font-medium">{{ locale.number(pagination.from)}}</span>
                        {{ translations.to }}
                        <span class="font-medium">{{ locale.number(pagination.to) }}</span>
                        {{ translations.of }}
                        <span class="font-medium">{{ locale.number( pagination.total) }}</span>
                        {{ translations.results }}
                    </p>
                </div>
                <!-- Group of Button Page -->
                <div v-if="HideButton">
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <!-- Button Page: Back -->
                        <component :is="previousPageUrl ? 'a' : 'div'" :class="{
                            'cursor-not-allowed text-gray-400': !previousPageUrl,
                            'text-gray-500 hover:bg-gray-300': previousPageUrl
                        }" :href="previousPageUrl" :dusk="previousPageUrl ? 'pagination-previous' : null"
                            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium"
                            @click.prevent="onClick(previousPageUrl)">
                            <span class="sr-only">{{ translations.previous }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg>
                        </component>
                        <!-- Number of pagination -->
                        <div v-for="(link, key) in pagination.links" :key="key" class="">
                            <slot name="link">
                                <component :is="link.url ? 'a' : 'div'" v-if="!isNaN(link.label) || link.label === '...'
                                    " :href="link.url" :dusk="link.url ? `pagination-${link.label}` : null"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium text-gray-700"
                                    :class="{
                                        'cursor-not-allowed': !link.url,
                                        'hover:bg-gray-300': link.url,
                                        'bg-gray-200': link.active,
                                    }" @click.prevent="onClick(link.url)">
                                    {{ link.label }}
                                </component>
                            </slot>
                        </div>
                        <!-- Button Page: Next -->
                        <component :is="nextPageUrl ? 'a' : 'div'" :class="{
                            'cursor-not-allowed text-gray-400': !nextPageUrl,
                            'text-gray-500 hover:bg-gray-300': nextPageUrl
                        }" :href="nextPageUrl" :dusk="nextPageUrl ? 'pagination-next' : null"
                            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium"
                            @click.prevent="onClick(nextPageUrl)">
                            <span class="sr-only">{{ translations.next }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                    clip-rule="evenodd" />
                            </svg>
                        </component>
                    </nav>
                </div>
            </div>
        </template>
    </nav>
</template>

<script setup lang="ts">
import PerPageSelector from "./PerPageSelector.vue"
import { computed, inject } from "vue"
import TableDownload from '@/Components/Table/TableDownload.vue'
import { getTranslations } from "./translations.js"
import { routeType } from "@/types/route"
import { trans } from 'laravel-vue-i18n'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

const translations = getTranslations()
const locale = inject('locale', aikuLocaleStructure)

const props = withDefaults(defineProps<{
    onClick: Function
    'perPageOptions'?: number[]
    onPerPageChange?: Function
    hasData: Boolean
    meta: {
        total: number
        links: {
            url: string
            label: string
            active: string
        }[]
        to: number
        from: number
        per_page: number
    }
    exportLinks?: {
        export: {
            route: routeType
        }
    }
}>(), {
    perPageOptions: () => [10, 25, 50, 100, 250, 500, 1000],
    onPerPageChange: () => {}
})

// console.log(props.modelOperations)

const pagination = computed(() => {
    return props.meta
})

const hasLinks = computed(() => {
    if (!("links" in pagination.value)) {
        return false
    }

    return pagination.value.links.length > 0
})

const hasPagination = computed(() => {
    return Object.keys(pagination.value).length > 0
})

const HideButton = computed(() => {
    return pagination.value.total > pagination.value.per_page
})

const previousPageUrl = computed(() => {
    if ("prev_page_url" in pagination.value) {
        return pagination.value.prev_page_url
    }

    return null
})

const nextPageUrl = computed(() => {
    if ("next_page_url" in pagination.value) {
        return pagination.value.next_page_url
    }

    return null
})

const perPage = computed(() => {
    return parseInt(pagination.value.per_page)
})
</script>
