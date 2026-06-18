<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
// import Row from 'primevue/row'
// import ColumnGroup from 'primevue/columngroup'
// import Tag from 'primevue/tag'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
// import Rating from 'primevue/rating'
import { FilterMatchMode } from '@primevue/core/api'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCircle } from '@fas'
import { faSearch, faExternalLink } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref } from 'vue'
library.add(faCircle, faSearch, faExternalLink)

const props = defineProps<{
    data: {}
}>()

const filters = ref({
    'global': {value: null, matchMode: FilterMatchMode.CONTAINS},
})

const selectedNotifications = ref([])

const selectIcon = (notifType: string) => {
    if (notifType === 'PalletReturn') {
        return 'fal fa-sign-out-alt'
    }
    else if (notifType === 'PalletDelivery') {
        return 'fal fa-truck-couch'
    }
}
</script>

<template>
    <div class="px-4 pb-6">
        <DataTable ref="dt" v-model:selection="selectedNotifications" :value="data.data" dataKey="id" :paginator="true"
            :rows="20"
            :filters="filters"
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            :rowsPerPageOptions="[5, 10, 20, 40]"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} products">
            <template #header headerStyle="background: #ff0000">
                <div class="flex flex-wrap gap-2 items-center justify-between">
                    <IconField>
                        <InputIcon>
                            <FontAwesomeIcon icon='fal fa-search' class='' fixed-width aria-hidden='true' />
                        </InputIcon>
                        <InputText v-model="filters['global'].value" :placeholder="trans('Search...')" />
                    </IconField>
                </div>
            </template>

            <Column selectionMode="multiple" style="width: 3rem" :exportable="false"></Column>

            <Column field="icon" style="width: 50px">
                <template #body="{ data }">
                    <div class="flex items-center gap-x-1 text-gray-500">
                        <FontAwesomeIcon v-if="selectIcon(data.type)" :icon='selectIcon(data.type)' class='' fixed-width aria-hidden='true' />
                        <span v-else>-</span>
                    </div>
                </template>
            </Column>

            <Column field="title" header="Name" sortable style="min-width: 12rem">
                <template #body="{ data }">
                    <div class="flex items-center gap-x-1">
                        <div>
                            <div class="group flex items-start">
                                <a :href="data.route" target="_blank" class="hover:underline leading-none">{{ data.title }}</a>
                                <FontAwesomeIcon v-if="data.read_at" icon='fas fa-circle' class='animate-pulse h-2 text-blue-500' fixed-width aria-hidden='true' />
                                <!-- <FontAwesomeIcon icon='fal fa-external-link' class='opacity-0 group-hover:opacity-100 h-3 text-gray-500' fixed-width aria-hidden='true' /> -->
                            </div>
                            <div class="text-gray-400 text-sm">{{ data.body }}</div>
                        </div>
                    </div>
                </template>
            </Column>
        </DataTable>
        <!-- <pre>{{ data }}</pre> -->
    </div>
</template>