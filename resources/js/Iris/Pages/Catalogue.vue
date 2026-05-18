<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'

import IrisLayout from '@/Layouts/Iris.vue'
import CatalogueLayout from '@/Iris/Layouts/CatalogueLayout.vue'

import TableIrisDepartment from '../Components/Tables/TableIrisDepartment.vue'
import TableIrisSubDepartment from '../Components/Tables/TableIrisSubDepartment.vue'
import TableIrisFamilies from '../Components/Tables/TableIrisFamilies.vue'
import TableIrisProducts from '../Components/Tables/TableIrisProducts.vue'
import TableIrisCollection from '../Components/Tables/TableIrisCollection.vue'

defineOptions({ layout: [IrisLayout, CatalogueLayout] })

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

const activeComponent = computed(() => componentMap[props.tabs.current] ?? null)
</script>

<template>
    <div class="p-3">
        <component v-if="activeComponent" :is="activeComponent" :data="data" :tab="tabs.current" />
    </div>
</template>