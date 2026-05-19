<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:44:55 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import Tabs from '@/Components/Navigation/Tabs.vue'
import { capitalize } from '@/Composables/capitalize'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { PageHeadingTypes } from '@/types/PageHeading'
import UnderConstruction from '@/Iris/Pages/Disclosure/UnderConstruction.vue'
import { computed, ref } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faSpider } from '@fal'
import TableCrawls from '@/Components/Tables/Grp/Org/Web/TableCrawls.vue'

library.add(faSpider)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: object
    }
    showcase: {}
    crawls: {}
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components = {
        showcase: UnderConstruction,
        crawls: TableCrawls
    }
    return components[currentTab.value]
})

</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" />
</template>
