<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 02 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableArtefacts from "@/Components/Tables/Grp/Org/Production/TableArtefacts.vue"
import TableArtefactFamilies from "@/Components/Tables/Grp/Org/Production/TableArtefactFamilies.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import ArtisanAssignments from "@/Components/Production/ArtisanAssignments.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, ref } from "vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    artefacts?: object
    families?: object
    history?: object
    move_to_department?: object
    move_to_family?: object
    set_batch_size?: object
    set_state?: object
    move_families_to_department?: object
    artisans: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => ({
    artefacts: TableArtefacts,
    families: TableArtefactFamilies,
    history: TableHistories,
}[currentTab.value]))

const componentProps = computed(() => ({
    artefacts: { moveToDepartment: props.move_to_department, moveToFamily: props.move_to_family, setBatchSize: props.set_batch_size, setState: props.set_state },
    families: { moveToDepartment: props.move_families_to_department },
    history: {},
}[currentTab.value] ?? {}))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <ArtisanAssignments :data="artisans" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" v-bind="componentProps" />
</template>
