<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import type { Component } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faFolderTree, faFolder, faCube, faAlbumCollection, faDotCircle as FarDotCircle } from '@far'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

import TableIrisDepartment from '../Components/Tables/TableIrisDepartment.vue'
import TableIrisSubDepartment from '../Components/Tables/TableIrisSubDepartment.vue'
import TableIrisFamilies from '../Components/Tables/TableIrisFamilies.vue'
import TableIrisProducts from '../Components/Tables/TableIrisProducts.vue'
import TableIrisCollection from '../Components/Tables/TableIrisCollection.vue'

import Button from '@/Components/Elements/Buttons/Button.vue'
import { faArrowLeft, faArrowRight, faWindowClose } from '@far'

library.add(faFolderTree, faFolder, faCube, faAlbumCollection, FarDotCircle)

const iconMap: Record<string, any> = {
    department: faFolderTree,
    sub_department: FarDotCircle,
    family: faFolder,
    product: faCube,
    collection: faAlbumCollection,
}

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

const componentMap: Record<string, Component> = {
    department: TableIrisDepartment,
    sub_department: TableIrisSubDepartment,
    family: TableIrisFamilies,
    product: TableIrisProducts,
    collection: TableIrisCollection,
}

const activeComponent = computed(() =>
    componentMap[props.tabs.current] ?? null
)

const scopeOrder = ['department', 'sub_department', 'family', 'product', 'collection']

const nextScopeMap: Record<string, string | null> = {
    department: 'sub_department',
    sub_department: 'family',
    family: 'product',
    collection: 'product',
    product: null,
}


type HistoryState = {
    scope: string
    parent?: string
    parent_key?: any
    parent_code?: string
    parent_name?: string
}

const history = ref<HistoryState[]>([
    { scope: props.tabs.current },
])

const loadingTab = ref<string | null>(null)

watch(
    () => props.tabs.current,
    (scope) => {
        const last = history.value.at(-1)
        if (last?.scope === scope) return

        history.value.push({ scope })
    }
)


const canBack = computed(() => history.value.length > 1)

const canNext = computed(() => {
    const idx = scopeOrder.indexOf(props.tabs.current)
    return idx >= 0 && idx < scopeOrder.length - 1
})


const page = usePage()
const canClear = computed(() => {
    return page.url.includes('?')
})


const navigate = (state: HistoryState) => {
    loadingTab.value = state.scope
    router.get(
        route(route().current() as string),
        {
            scope: state.scope,
            parent: state.parent,
            parent_key: state.parent_key,
            parent_code: state.parent_code,
            parent_name: state.parent_name,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                loadingTab.value = null
            },
        }
    )
}


const changeTab = (scope: string) => {
    const last = history.value.at(-1)

    const state: HistoryState = {
        scope,
        parent: last?.parent,
        parent_key: last?.parent_key,
        parent_code: last?.parent_code,
        parent_name: last?.parent_name,
    }

    history.value.push(state)
    navigate(state)
}


const goBack = () => {
    if (!canBack.value) return

    history.value.pop()
    const prev = history.value.at(-1)
    if (!prev) return

    navigate(prev)
}

const goNext = () => {
    const idx = scopeOrder.indexOf(props.tabs.current)
    const nextScope = scopeOrder[idx + 1]
    if (!nextScope) return

    const last = history.value.at(-1)

    history.value.push({
        scope: nextScope,
        parent: last?.parent,
        parent_key: last?.parent_key,
        parent_code: last?.parent_code,
        parent_name: last?.parent_name,
    })

    navigate(history.value.at(-1)!)
}

const clearScope = () => {
    history.value = [{ scope: 'department' }]
    navigate({ scope: 'department' })
}


const onSelectParent = (parentType: string, parentId: any, parentCode?: string, parentName?: string) => {
    const nextScope = nextScopeMap[parentType]
    if (!nextScope) return

    const state: HistoryState = {
        scope: nextScope,
        parent: parentType,
        parent_key: parentId,
        parent_code: parentCode,
        parent_name: parentName,
    }

    history.value.push(state)
    navigate(state)
}
</script>

<template>
    <div class="max-w-7xl mx-auto my-8">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">

            <!-- Top Bar -->
            <div class="flex items-center justify-between px-2 h-fit border-b border-gray-200">

                <!-- LEFT SIDE: Nav-style Tabs -->
                <nav class="hidden md:flex items-center space-x-4 flex-1 overflow-x-auto">
                    <button v-for="tab in tabs.navigation" :key="tab.key + tabs.current"
                        type="button"
                        @click="changeTab(tab.key)"
                        :class="[
                            'relative flex items-center py-2 font-medium text-xs md:text-sm transition duration-150 ease-in-out border-b-2 rounded-t-md gap-2 px-4 whitespace-nowrap',
                            tab.key === tabs.current
                                ? 'text-slate-600 border-slate-500'
                                : 'text-gray-600 border-transparent hover:text-slate-600 hover:border-slate-300'
                        ]">
                        <LoadingIcon v-if="loadingTab === tab.key" class="h-4 w-4" />
                        <FontAwesomeIcon v-else :icon="iconMap[tab.key] ?? FarDotCircle" fixed-width class="h-4 w-4" />
                        <span>{{ tab.label }}</span>
                    </button>
                </nav>

                <!-- Mobile: Select Dropdown -->
                <div class="block md:hidden flex-1 py-2">
                    <select class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" :value="tabs.current"
                        @change="changeTab(($event.target as HTMLSelectElement).value)">
                        <option v-for="tab in tabs.navigation" :key="tab.key" :value="tab.key">
                            {{ tab.label }}
                        </option>
                    </select>
                </div>

                <!-- RIGHT SIDE -->
                <div class="flex items-center gap-1 pl-2">
                    <Button type="transparent" :disabled="!canBack" :icon="faArrowLeft" @click="goBack" />
                    <Button type="transparent" :disabled="!canNext" :icon="faArrowRight" @click="goNext" />
                    <Button type="transparent" :disabled="!canClear" :icon="faWindowClose" @click="clearScope" />
                </div>
            </div>

            <!-- Table -->
            <div class="p-3 iris-catalouge">
                <component v-if="activeComponent" :is="activeComponent" :data="data" :tab="tabs.current"
                    @select-department="(id: any, code?: string, name?: string) => onSelectParent('department', id, code, name)"
                    @select-sub-department="(id: any, code?: string, name?: string) => onSelectParent('sub_department', id, code, name)"
                    @select-family="(id: any, code?: string, name?: string) => onSelectParent('family', id, code, name)"
                    @select-collection="(id: any, code?: string, name?: string) => onSelectParent('collection', id, code, name)" />
            </div>

        </div>
    </div>
</template>



<style scoped lang="scss">
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

:deep(.iris-catalouge .table-query-builder) {
    @apply p-0
}


:deep(.iris-catalouge .primaryLink) {
    background: v-bind('`linear-gradient(to top, #fcd34d, #fcd34d)`');
    cursor: pointer;

    &:hover,
    &:focus {
        color: #374151;
    }

    @apply focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1 py-0.5
}

:deep(.iris-catalouge .secondaryLink) {
    background: v-bind('`linear-gradient(to top, #fcd34d}, #fcd34d + "AA"})`');

    &:hover,
    &:focus {
        color: v-bind('`#fcd34d`');
    }

    @apply focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1 py-0.5
}
</style>