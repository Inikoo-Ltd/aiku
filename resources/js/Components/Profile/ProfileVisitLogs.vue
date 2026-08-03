<script setup lang='ts'>
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'


import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import UserAgent from "@/Components/Elements/Info/UserAgent.vue"
import { useFormatTime } from '@/Composables/useFormatTime'

const props = defineProps<{
    data: {}
}>()
</script>

<template>
    <div>
        <!-- Profile Visit Logs -->

        <DataTable
            ref="_dt"
            :value="data.data"
            dataKey="id"
            :paginator="true"
            :rows="15"
            scrollable
            removableSort
            paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
            :rowsPerPageOptions="[15, 25, 40]"
            currentPageReportTemplate="Showing {first} to {last} of {totalRecords} products"
        >
            <Column field="username" header="Username" sortable class="overflow-hidden transition-all">
                <!-- <template #body="{ data }">
                    <div class="relative">
                        <Transition name="spin-to-down" mode="out-in">
                            <div :key="data.code">
                                {{ data.code }}
                            </div>
                        </Transition>
                    </div>
                </template> -->
            </Column>


            <Column field="user_agent" header="User Agent" class="overflow-hidden transition-all">
                <template #body="{ data }">
                    <UserAgent :data="data.user_agent" />
                </template>
            </Column>

            <Column field="location" header="Location" sortable class="overflow-hidden transition-all">
                <template #body="{ data }">
                    <AddressLocation :data="data.location" />
                </template>
            </Column>

            <Column field="datetime" header="Date" sortable class="overflow-hidden transition-all">
                <template #body="{ data }">
                    {{ useFormatTime(data.datetime) }}
                </template>
            </Column>

        </DataTable>
    </div>
    <!-- <pre>{{ data.data }}</pre> -->
</template>