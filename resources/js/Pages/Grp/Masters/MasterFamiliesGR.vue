<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 02 Feb 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableMasterFamilies from "@/Components/Tables/Grp/Goods/TableMasterFamilies.vue"
import { capitalize } from "@/Composables/capitalize"
import { ref, computed } from "vue"
import { faCheckCircle, faTimesCircle } from '@fal'
import { library } from "@fortawesome/fontawesome-svg-core"
import { PageHeadingTypes } from '@/types/PageHeading'
import { useTabChange } from '@/Composables/tab-change'
import Tabs from "@/Components/Navigation/Tabs.vue"

library.add(faCheckCircle, faTimesCircle)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    }
    with?: {}
    without?: {}
    shopsData: {}
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const currentData = computed(() => (props as any)[currentTab.value])


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <TableMasterFamilies :key="currentTab" :tab="currentTab" :data="currentData" />
</template>
