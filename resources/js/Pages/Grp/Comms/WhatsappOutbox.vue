<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import type { Component } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import WhatsappOutboxShowcase from "@/Components/Comms/WhatsappOutboxShowcase.vue"
import TableWhatsappOutboxMessages from "@/Components/Tables/TableWhatsappOutboxMessages.vue"
import TableWhatsappBulkRuns from "@/Components/Tables/TableWhatsappBulkRuns.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTachometerAltFast, faSeedling, faBroadcastTower, faTimes, faMailBulk } from "@fal"
import { faPaperPlane } from "@far"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"

library.add(faTachometerAltFast, faSeedling, faBroadcastTower, faTimes, faMailBulk, faPaperPlane, faWhatsapp)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: object
    messages?: object
    bulk_runs?: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Record<string, Component> = {
        showcase: WhatsappOutboxShowcase,
        messages: TableWhatsappOutboxMessages,
        bulk_runs: TableWhatsappBulkRuns,
    }

    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>
