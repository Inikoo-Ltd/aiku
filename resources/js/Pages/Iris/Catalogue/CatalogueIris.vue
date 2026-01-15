<script setup lang="ts">
import { computed } from 'vue'
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
    data : any
}>()

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

/* const changeTab = (key: string) => {
    if (key === props.tabs.current) return

    const url = new URL(window.location.href)
    url.searchParams.set('scope', key)

    window.history.pushState({}, '', url.toString())
}
 */


const componentMap: Record<string, any> = {
    departments: TableIrisDepartment,
    sub_departments: TableIrisSubDepartment,
    families: TableIrisFamilies,
    products: null,
}

const activeComponent = computed(() => {
    return componentMap[props.tabs.current] ?? null
})
</script>

<template>
    <!-- Tabs -->
    <div class="flex items-center gap-1 border-b border-gray-200 text-sm">
        <button
            v-for="tab in tabs.navigation"
            :key="tab.key"
            @click="changeTab(tab.key)"
            class="relative px-3 py-2 font-medium transition"
            :class="[
                tab.key === tabs.current
                    ? 'text-gray-900'
                    : 'text-gray-500 hover:text-gray-700'
            ]"
        >
            {{ tab.label }}

            <span
                v-if="tab.key === tabs.current"
                class="absolute inset-x-0 -bottom-px h-[2px] bg-gray-900"
            />
        </button>
    </div>

    <!-- Active table -->
    <component
        v-if="activeComponent"
        :is="activeComponent"
        class="mt-5"
        :data="data[tabs.current]"
        :tab="tabs.current"
    />
</template>
