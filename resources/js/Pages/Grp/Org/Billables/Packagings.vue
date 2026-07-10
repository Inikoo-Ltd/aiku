<!--
  -  Author: Andi Ferdiawan
  -  Created: Thu, 09 Jul 2026 11:00:00 Central Indonesia Time, Bali, Indonesia
  -  Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import { computed, ref } from 'vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import { useTabChange } from '@/Composables/tab-change'
import TablePackagings from '@/Components/Tables/Grp/Org/Billables/TablePackagings.vue'
import TableLeaflets from '@/Components/Tables/Grp/Org/Billables/TableLeaflets.vue'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    createActions?: Record<string, {}> | null
    packagings?: {}
    leaflets?: {}
}>()

const currentTab = ref<string>(props.tabs?.current ?? 'packagings')
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const pageHeadWithActions = computed(() => ({
    ...props.pageHead,
    actions: props.createActions?.[currentTab.value]
        ? [props.createActions[currentTab.value]]
        : [],
}))

const component = computed(() => {
    const components: Record<string, unknown> = {
        packagings: TablePackagings,
        leaflets: TableLeaflets,
    }

    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHeadWithActions" />
    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

    <component
        :is="component"
        :key="currentTab"
        :tab="currentTab"
        :data="props[currentTab as keyof typeof props]"
    />
</template>
