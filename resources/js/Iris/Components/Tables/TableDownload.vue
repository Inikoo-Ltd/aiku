<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { ref, watchEffect } from 'vue'
import {usePage} from '@inertiajs/vue3'

import ButtonWithDropdown from "./ButtonWithDropdown.vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDownload } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faDownload)

const props = defineProps([
    'exportLinks'
])

const urlPage = ref(location)
const cleanedSearchUrl = ref(location.search ? location.search.slice(1) : '')

watchEffect(() => {
    urlPage.value = location
    cleanedSearchUrl.value = location.search ? location.search.slice(1) : ''
    usePage().url  // to trigger watchEffect on filter table changed
})


</script>

<template>
    <ButtonWithDropdown dusk="table-download-dropdown" class="w-auto">
        <template #button>
            <div class="h-auto aspect-square flex justify-center items-center" :title="trans('Export Table')">
                <FontAwesomeIcon icon="fas fa-download" class="text-xs text-gray-400" aria-hidden="true" />
            </div>
        </template>

        <!-- The popup -->
        <div role="menu" aria-orientation="horizontal" aria-labelledby="table-download-data" class="grid w-40 min-w-max">
            <!-- Table: XLSX -->
            <a :href="
                    exportLinks.export?.route
                        ? `${urlPage.origin}${urlPage.pathname}${route(exportLinks.export?.route?.name)}?type=xlsx&${cleanedSearchUrl}`
                        : `${urlPage.origin}${urlPage.pathname}/export?type=xlsx&${cleanedSearchUrl}`
                "
                :dusk="`add-search-row-1`"
                role="menuitem" download
                class="text-left w-full px-4 py-2 text-sm text-gray-600 hover:bg-gray-200 hover:text-gray-800"
            >
                Export as Excel (.xlsx)
            </a>

            <!-- Table: CSV -->
            <a :href="
                    exportLinks.export?.route
                        ? `${urlPage.origin}${urlPage.pathname}${route(exportLinks.export?.route?.name)}?type=csv&${cleanedSearchUrl}`
                        : `${urlPage.origin}${urlPage.pathname}/export?type=csv&${cleanedSearchUrl}`
                "
                :dusk="`add-search-row-2`"
                role="menuitem" download
                class="text-left w-full px-4 py-2 text-sm text-gray-600 hover:bg-gray-200 hover:text-gray-800"
            >
                Export as CSV (.csv)
            </a>
        </div>
    </ButtonWithDropdown>
</template>
