<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, defineAsyncComponent, ref } from 'vue'
import type { Component } from 'vue'

import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import TableArtefactRecipe from "@/Components/Tables/Grp/Org/Production/TableArtefactRecipe.vue"
import TableArtefactCompliance from "@/Components/Tables/Grp/Org/Production/TableArtefactCompliance.vue"
import ArtefactShowcase from "@/Components/Showcases/Grp/ArtefactShowcase.vue"

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: {}
    manufacture_tasks?:{}
    compliance?: {}
    history?: {}


}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components: Component = {
        showcase: ArtefactShowcase,
        manufacture_tasks: TableArtefactRecipe,
        compliance: TableArtefactCompliance,
        history: TableHistories,
    }

    return components[currentTab.value]

})

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>