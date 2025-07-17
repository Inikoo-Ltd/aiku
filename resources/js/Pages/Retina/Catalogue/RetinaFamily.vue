<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faBullhorn,
    faCameraRetro,
    faCube,
    faFolder,
    faMoneyBillWave,
    faProjectDiagram,
    faTag,
    faUser,
    faBrowser
} from "@fal"
import { faExclamationTriangle } from "@fas"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import FamilyShowcase from "@/Components/Showcases/Grp/FamilyShowcase.vue"

library.add(
    faFolder,
    faCube,
    faCameraRetro,
    faTag,
    faBullhorn,
    faProjectDiagram,
    faUser,
    faMoneyBillWave,
    faBrowser, faExclamationTriangle
)

const props = defineProps<{
    title: string
    pageHead: object
    tabs: {
        current: string
        navigation: object
    }
    showcase: object
}>()

const currentTab = ref(props.tabs.current)


const handleTabUpdate = (tabSlug: string) => {
    useTabChange(tabSlug, currentTab)
}

const component = computed(() => {
    const components = {
        showcase: FamilyShowcase,
    }
    return components[currentTab.value] ?? ModelDetails
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" />
</template>
