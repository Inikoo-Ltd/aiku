<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'

import IrisLayout from '@/Layouts/Iris.vue'
import CatalogueLayout from '@/Iris/Layouts/CatalogueLayout.vue'

import TableIrisDepartment from '../Components/Catalogue/TableIrisDepartment.vue'
import TableIrisSubDepartment from '../Components/Catalogue/TableIrisSubDepartment.vue'
import TableIrisFamilies from '../Components/Catalogue/TableIrisFamilies.vue'
import TableIrisProducts from '../Components/Catalogue/TableIrisProducts.vue'
import TableIrisCollection from '../Components/Catalogue/TableIrisCollection.vue'

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