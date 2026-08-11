<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Tue, 11 Aug 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import type { Component } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import TrafficSourceShowcase from '@/Components/Showcases/Grp/TrafficSourceShowcase.vue'
import TableCustomers from '@/Components/Tables/Grp/Org/CRM/TableCustomers.vue'
import TableOrders from '@/Components/Tables/Grp/Org/Ordering/TableOrders.vue'
import { PageHeadingTypes } from '@/types/PageHeading'
import { capitalize } from '@/Composables/capitalize'
import { useTabChange } from '@/Composables/tab-change'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    overview?: any
    customers?: {}
    orders?: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        overview: TrafficSourceShowcase,
        customers: TableCustomers,
        orders: TableOrders,
    }

    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <component
        :is="component"
        :data="props[currentTab as keyof typeof props]"
        :tab="currentTab"
    />
</template>
