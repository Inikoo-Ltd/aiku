<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import type { Component } from 'vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import SpaceShowcase from '@/Components/Fulfilment/SpaceShowcase.vue'
import { faInfoCircle } from '@fas'
library.add( faInfoCircle)


const props = defineProps<{
    title: string,
    pageHead: {},
    tabs: {
        current: string
        navigation: {}
    }
    showcase?: {},

}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        showcase: SpaceShowcase
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