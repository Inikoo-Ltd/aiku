<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Link } from '@inertiajs/vue3'
import { inject, ref, computed } from 'vue'
import Icon from '../Icon.vue'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { router } from '@inertiajs/vue3'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { Icon as IconTS } from '@/types/Utils/Icon'

const props = defineProps<{
    data: {
        [key: string]: {
            label: string
            sublabel: string
            count: number
            cases: {
                key: string
                label: string
                value?: number
                icon: string | string[]
                icon_state: IconTS
                class?: string
                route?: {
                    name: string
                    parameters?: object
                }
            }[]
        }
    }
}>()

const states = computed(() => {
    const firstGroup = Object.values(props.data)[0] as any
    if (!firstGroup?.cases) return []

    return Object.values(firstGroup.cases).map((item: any) => ({
        key: item.key,
        label: item.label
    }))
})

const rows = computed(() => {
    return Object.values(props.data).map((group: any) => ({
        label: group.label,
        total: group.count,
        ...group.cases
    }))
})

const goToRoute = (item: any) => {
    if (!item?.route?.name) return
    router.visit(route(item.route.name, item.route.parameters))
}
</script>

<template>
<div class="p-4">
    <DataTable
        :value="rows"
        responsiveLayout="stack"
        breakpoint="768px"
        stripedRows
        class="p-datatable-sm text-sm"
    >
        <!-- CHANNEL -->
        <Column>
            <template #header>
                <div class="w-full flex justify-start font-semibold">
                    Channel
                </div>
            </template>

            <template #body="{ data }">
                <div
                    class="flex justify-between md:justify-start"
                    data-label="Channel"
                >
                    <span class="font-semibold text-gray-700">
                        {{ data.label }}
                    </span>
                </div>
            </template>
        </Column>

        <!-- TOTAL -->
        <Column>
            <template #header>
                <div class="w-full flex justify-center font-semibold">
                    Total
                </div>
            </template>

            <template #body="{ data }">
                <div
                    class="flex justify-between md:justify-center"
                    data-label="Total"
                >
                    <span class="font-bold text-indigo-600">
                        {{ data.total ?? 0 }}
                    </span>
                </div>
            </template>
        </Column>

        <!-- DYNAMIC STATES -->
        <Column
            v-for="state in states"
            :key="state.key"
        >
            <template #header>
                <div class="w-full flex justify-center font-semibold">
                    {{ state.label }}
                </div>
            </template>

            <template #body="{ data }">
                <div
                    class="flex justify-between md:justify-center items-center gap-2
                           cursor-pointer hover:bg-gray-100 rounded-md py-1 transition"
                    :data-label="state.label"
                    @click="goToRoute(data[state.key])"
                >
                    <div class="flex items-center gap-2">
                        <Icon
                            v-if="data[state.key]?.icon_state"
                            :data="data[state.key].icon_state"
                        />

                        <FontAwesomeIcon
                            v-else-if="data[state.key]?.icon"
                            :icon="data[state.key].icon"
                            class="text-gray-400"
                        />

                        <span class="font-medium">
                            {{ data[state.key]?.value ?? 0 }}
                        </span>
                    </div>
                </div>
            </template>
        </Column>

    </DataTable>
</div>
</template>