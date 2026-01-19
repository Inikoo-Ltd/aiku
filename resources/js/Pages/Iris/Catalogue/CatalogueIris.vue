<script setup lang="ts">
import { computed, inject } from 'vue'
import { router } from '@inertiajs/vue3'

import RetinaTableDepartments from '@/Components/Tables/Iris/TableIrisDepartment.vue'

import Table from '@/Components/Rental/Table.vue'
import TableIrisDepartment from '@/Components/Tables/Iris/TableIrisDepartment.vue';
import TableIrisSubDepartment from '@/Components/Tables/Iris/TableIrisSubDepartment.vue';
import TableIrisFamilies from '@/Components/Tables/Iris/TableIrisFamilies.vue';

const props = defineProps<{
    tabs: {
        current: string
        navigation: {
            key: string
            label: string
        }[]
    }
    data: any
}>()

const layout: any = inject("layout", {});

const changeTab = (key: string) => {
    if (key === props.tabs.current) return

    router.get(
        route(route().current() as string),
        { scope: key },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}


const componentMap: Record<string, any> = {
    departments: TableIrisDepartment,
    sub_departments: TableIrisSubDepartment,
    families: TableIrisFamilies,
    products: null,
}

const activeComponent = computed(() => {
    return componentMap[props.tabs.current] ?? null
})


const onSelectDepartment = (department: any) => {
    router.get(
        route(route().current() as string),
        {
            scope: 'sub_departments',
            parent: department,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}



</script>

<template>
    <div class="max-w-7xl mx-auto my-8">
        <!-- Container -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">

            <!-- Top Bar -->
            <div class="flex items-center gap-2 px-4 h-11 border-b border-gray-100">
                <button v-for="tab in tabs.navigation" :key="tab.key" @click="changeTab(tab.key)"
                    class="px-3 h-7 text-sm font-medium rounded-lg transition" :class="[
                        tab.key === tabs.current
                            ? 'bg-gray-900 text-white'
                            : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                    ]">
                    {{ tab.label }}
                </button>
            </div>

            <!-- Table Area -->
            <div class="p-3 iris-catalouge">
                <component 
                    v-if="activeComponent" 
                    :is="activeComponent" 
                    :data="data[tabs.current]"
                    :tab="tabs.current" 
                    @select-department="onSelectDepartment"
                />
            </div>

        </div>
    </div>
</template>


<style scoped>
/* Base table */
/* Reset semua border */
:deep(.iris-catalouge table) {
    @apply w-full text-sm border-collapse;
    border: none;
}

:deep(.iris-catalouge th),
:deep(.iris-catalouge td) {
    @apply px-3 py-2;
    border: none !important;
}

/* Header */
:deep(.iris-catalouge th) {
    @apply text-left font-semibold whitespace-nowrap;
    background-color: var(--theme-color-4);
    color: var(--theme-color-5);
}

/* HANYA border antar row */
:deep(.iris-catalouge tbody tr) {
    border-bottom: 1px solid theme('colors.gray.100');
}

/* Hilangkan border row terakhir */
:deep(.iris-catalouge tbody tr:last-child) {
    border-bottom: none;
}

/* Hover */
:deep(.iris-catalouge tbody tr:hover) {
    @apply bg-gray-50 transition;
}

/* Alignment helpers */
:deep(.iris-catalouge .text-center) {
    text-align: center;
}

:deep(.iris-catalouge .text-right) {
    text-align: right;
}

/* Wrapper */
:deep(.iris-catalouge .tableWrapper) {
    @apply overflow-auto rounded-lg;
    border: none;
}

/* Custom header cell */
:deep(.iris-catalouge .thead-avatar) {
    @apply px-5 w-16;
}

:deep(.iris-catalouge .table-query-builder){
    @apply p-0
}


:deep(.iris-catalouge .primaryLink){
    background: v-bind('`linear-gradient(to top, #fcd34d, #fcd34d)`');

    &:hover,
    &:focus {
        color: #374151;
    }

    @apply focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1 py-0.5
}

:deep(.iris-catalouge .secondaryLink){
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[6]}, ${layout.app.theme[6] + "AA"})`');

    &:hover,
    &:focus {
        color: v-bind('`${layout.app.theme[7]}`');
    }

    @apply focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1 py-0.5
}
</style>