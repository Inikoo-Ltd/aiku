<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 Feb 2023 14:32:57 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Tabs from '@/Components/Navigation/Tabs.vue'
import { computed, ref } from 'vue'
import type { Component } from 'vue'
import { useTabChange } from '@/Composables/tab-change'

import { faHandsHelping, faBan, faCheckCircle, faList } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import DispatchDashboard from '@/Components/Warehouse/DispatchDashboard.vue'
library.add(faHandsHelping, faBan, faCheckCircle, faList)


const props = defineProps<{
    title: string
    pageHead: {}
    tabs: {
        current: string
        navigation: {}
    }
    dashboard: {
        [key: string]: {
            label: string
            count: number
            cases: {
                key: string
                label: string
                value?: number
                icon: string | string[]
                class?: string
                route?: {
                    name: string
                    parameters?: object
                }
            }[]
        }
    }
}>()


let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const component: Component = computed(() => {
    const components = {
        ['dashboard' as string]: DispatchDashboard,
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
