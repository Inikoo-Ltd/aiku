<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import DummyComponent from "@/Components/DummyComponent.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, defineAsyncComponent, ref } from 'vue'
import type { Component } from 'vue'

import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import TableOutboxes from '@/Components/Tables/TableOutboxes.vue'
import TableMailshots from '@/Components/Tables/TableMailshots.vue'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTachometerAltFast, faInboxOut, faFolder, faEnvelope } from "@fal"
import TableDispatchedEmails from '@/Components/Tables/TableDispatchedEmails.vue'
library.add(faTachometerAltFast, faInboxOut, faFolder, faEnvelope)

// import FileShowcase from '@/xxxxxxxxxxxx'

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase: object
    outboxes: object
    mailshots: object
    dispatched_emails: object
}>()
const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components: Component = {
        showcase: DummyComponent,
        outboxes: TableOutboxes,
        mailshots: TableMailshots,
        dispatched_emails: TableDispatchedEmails
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
