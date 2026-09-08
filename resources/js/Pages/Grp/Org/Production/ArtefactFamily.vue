<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableArtefacts from "@/Components/Tables/Grp/Org/Production/TableArtefacts.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { computed, ref } from "vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import { routeType } from "@/types/route"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    artefacts?: object
    history?: object
    move_to_family?: object
    department?: { code: string; name: string; route: routeType }
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => ({
    artefacts: TableArtefacts,
    history: TableHistories,
}[currentTab.value]))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div v-if="department" class="px-4 py-2 text-sm text-gray-500">
        {{ ctrans('Department') }}:
        <Link :href="route(department.route.name, department.route.parameters)" class="secondaryLink">{{ department.name }}</Link>
    </div>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :moveToFamily="move_to_family" />
</template>
