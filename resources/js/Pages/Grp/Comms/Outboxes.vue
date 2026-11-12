<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import type { Component } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableOutboxes from "@/Components/Tables/TableOutboxes.vue"
import CustomerNotificationWhatsappOutboxes from "@/Components/Comms/CustomerNotificationWhatsappOutboxes.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInboxOut, faSortAlt, faProjectDiagram, faPhoneVolume, faBars, faSeedling, faTimes, faChartNetwork, faEnvelope } from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"

library.add(faInboxOut, faSortAlt, faProjectDiagram, faPhoneVolume, faBars, faSeedling, faTimes, faChartNetwork, faEnvelope, faWhatsapp)


const props = defineProps<{
    data?: object
    title: string
    pageHead: PageHeadingTypes
    tabs?: TSTabs
    email?: object
    whatsapp?: object
}>()

const currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Record<string, Component> = {
        email: TableOutboxes,
        whatsapp: CustomerNotificationWhatsappOutboxes,
    }

    return currentTab.value ? components[currentTab.value] : null
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <div v-if="tabs">
        <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
        <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
    </div>
    <TableOutboxes v-else :data="data" />
</template>
