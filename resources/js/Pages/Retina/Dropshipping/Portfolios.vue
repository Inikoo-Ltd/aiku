<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, defineAsyncComponent, ref } from 'vue'
import type { Component } from 'vue'

import { PageHeading as TSPageHeading } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import TablePortfolios from "@/Components/Tables/Grp/Org/Catalogue/TablePortfolios.vue";

// import FileShowcase from '@/xxxxxxxxxxxx'

const props = defineProps<{
    title: string,
    pageHead: TSPageHeading
    tabs: TSTabs
    products: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components: Component = {
        // showcase: FileShowcase
        // products: TableProducts
    }

    return components[currentTab.value]

})

</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
<!--     <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />-->
<!--     <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />-->
    <TablePortfolios :data="props.products" :tab="'products'" />
</template>
