<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import { router, usePage  } from '@inertiajs/vue3'
import type { Component } from 'vue'

import TableIrisDepartment from '@/Components/Tables/Iris/TableIrisDepartment.vue'
import TableIrisSubDepartment from '@/Components/Tables/Iris/TableIrisSubDepartment.vue'
import TableIrisFamilies from '@/Components/Tables/Iris/TableIrisFamilies.vue'
import TableIrisProducts from '@/Components/Tables/Iris/TableIrisProducts.vue'

import Button from '@/Components/Elements/Buttons/Button.vue'
import { faArrowLeft, faArrowRight, faWindowClose } from '@far'


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

const layout: any = inject('layout', {})


const componentMap: Record<string, Component> = {
    department: TableIrisDepartment,
    sub_department: TableIrisSubDepartment,
    family: TableIrisFamilies,
    product: TableIrisProducts,
}

const activeComponent = computed(() =>
    componentMap[props.tabs.current] ?? null
)


const scopeOrder = ['department', 'sub_department', 'family', 'product']

const nextScopeMap: Record<string, string | null> = {
    department: 'sub_department',
    sub_department: 'family',
    family: 'product',
    product: null,
}


type HistoryState = {
    scope: string
    parent_type?: string
    parent_key?: any
}

const history = ref<HistoryState[]>([
    { scope: props.tabs.current },
])

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
    router.get(
        route(route().current() as string),
        {
            scope: state.scope,
            parent_type: state.parent_type,
            parent_key: state.parent_key,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}


const changeTab = (scope: string) => {
    history.value.push({ scope })
    navigate({ scope })
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
        parent_type: last?.parent_type,
        parent_key: last?.parent_key,
    })

    navigate(history.value.at(-1)!)
}

const clearScope = () => {
    history.value = [{ scope: 'department' }]
    navigate({ scope: 'department' })
}


const onSelectParent = (parentType: string, parentId: any) => {
    const nextScope = nextScopeMap[parentType]
    if (!nextScope) return

    const state: HistoryState = {
        scope: nextScope,
        parent_type: parentType,
        parent_key: parentId,
    }

    history.value.push(state)
    navigate(state)
}
</script>

<template>
    <div class="max-w-7xl mx-auto my-8">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">

            <!-- Top Bar -->
            <div class="flex items-center justify-between px-4 h-11 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <Button v-for="tab in tabs.navigation" :key="tab.key"
                        :type="tab.key === tabs.current ? 'primary' : 'secondary'" :label="tab.label"
                        @click="changeTab(tab.key)" />
                </div>

                <div class="flex items-center gap-2">
                    <Button type="transparent" :disabled="!canBack" :icon="faArrowLeft" @click="goBack" />

                    <Button type="transparent" :disabled="!canNext" :icon="faArrowRight" @click="goNext" />

                    <Button type="transparent" :disabled="!canClear" :icon="faWindowClose" @click="clearScope" />
                </div>
            </div>

            <!-- Table -->
            <div class="p-3 iris-catalouge">
                <component v-if="activeComponent" :is="activeComponent" :data="data" :tab="tabs.current"
                    @select-department="id => onSelectParent('department', id)"
                    @select-sub-department="id => onSelectParent('sub_department', id)"
                    @select-family="id => onSelectParent('family', id)" />
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

:deep(.iris-catalouge .table-query-builder) {
    @apply p-0
}


:deep(.iris-catalouge .primaryLink) {
    background: v-bind('`linear-gradient(to top, #fcd34d, #fcd34d)`');

    &:hover,
    &:focus {
        color: #374151;
    }

    @apply focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1 py-0.5
}

:deep(.iris-catalouge .secondaryLink) {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[6]}, ${layout.app.theme[6] + "AA"})`');

    &:hover,
    &:focus {
        color: v-bind('`${layout.app.theme[7]}`');
    }

    @apply focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1 py-0.5
}
</style>