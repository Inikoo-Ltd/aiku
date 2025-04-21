<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 23 Feb 2024 09:56:34 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup  lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus } from "@fas"
import { faSeedling, faShare, faSpellCheck, faCheck, faCheckDouble, faCross } from "@fal"

import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import type { Component } from "vue"
import type { Navigation } from "@/types/Tabs"
import { useTabChange } from "@/Composables/tab-change"
import TablePalletDeliveries from "@/Components/Tables/Retina/TableRetinaPalletDeliveries.vue"
import TablePalletUploads from "@/Components/Tables/TablePalletUploads.vue"


library.add(faPlus, faSeedling, faShare, faSpellCheck, faCheck, faCheckDouble, faCross)

const props = defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: Navigation
    }
    deliveries?: {}
    uploads?: {}
}>()
const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        deliveries: TablePalletDeliveries,
        uploads: TablePalletUploads
    }

    return components[currentTab.value]
})

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
</template>
