<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { useLocaleStore } from '@/Stores/locale'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed, defineAsyncComponent, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import type { Component } from 'vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import TablePurgedOrders from '@/Components/Tables/Grp/Org/Ordering/TablePurgedOrders.vue'
import SpaceShowcase from '@/Components/Fulfilment/SpaceShowcase.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import { faInfoCircle } from '@fas'
library.add( faInfoCircle)

const locale = useLocaleStore()

const props = defineProps<{
    title: string,
    pageHead: {},
    tabs: {
        current: string
        navigation: {}
    }
    showcase?: {},
    history?: {},

}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        showcase: SpaceShowcase,
        history: TableHistories
    }
    return components[currentTab.value]

});

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>