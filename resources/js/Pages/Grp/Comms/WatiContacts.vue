<!--
  - Author: andiferdiawan (https://github.com/andiferdiawan)
  - Created: Wednesday, 21 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, andiferdiawan
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import type { Component } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableWatiContacts from "@/Components/Tables/TableWatiContacts.vue"
import TableCustomersForWati from "@/Components/Tables/TableCustomersForWati.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserSlash } from "@fal"

library.add(faUserSlash)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    routes?: { add: string; bulk_add: string }
    all?: object
    linked?: object
    wati_only?: object
    not_in_wati?: object
}>()

const currentTab = ref(props.tabs.current)

const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed((): Component => {
    const map: Record<string, Component> = {
        all:          TableWatiContacts,
        linked:       TableWatiContacts,
        wati_only:    TableWatiContacts,
        not_in_wati:  TableCustomersForWati,
    }
    return map[currentTab.value] ?? TableWatiContacts
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component
        :is="component"
        :data="props[currentTab as keyof typeof props]"
        :tab="currentTab"
        :routes="routes"
    />
</template>
