<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableCollections from "@/Components/Tables/Grp/Org/Catalogue/TableCollections.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from '@/types/route'
import { computed, ref } from 'vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import { useTabChange } from '@/Composables/tab-change'


const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    }
    data: {}
    index?: {}
    sales?: {}
    formData: {}
    website_domain: string
    routes: Array<routeType>
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: any = {
        index: TableCollections,
        sales: TableCollections,
    }

    return components[currentTab.value]
})

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <component
        :is="component"
        :key="currentTab"
        :tab="currentTab"
        :data="props[currentTab]"
        :website_domain="website_domain"
        :routes="routes"
    />

</template>
