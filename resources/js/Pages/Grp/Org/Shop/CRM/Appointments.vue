<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableAppointments from "@/Components/Tables/Grp/Org/CRM/TableAppointments.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCalendar, faHistory, faTimesCircle, faHourglassHalf } from "@fal"

library.add(faCalendar, faHistory, faTimesCircle, faHourglassHalf)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    }
    requested?: {}
    upcoming?: {}
    past?: {}
    cancelled?: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <TableAppointments :key="currentTab" :data="props[currentTab as 'requested' | 'upcoming' | 'past' | 'cancelled']" :tab="currentTab" />
</template>
