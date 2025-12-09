<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { useTabChange } from "@/Composables/tab-change"
import { computed, ref } from "vue"
import type { Component } from 'vue'
import Tabs from "@/Components/Navigation/Tabs.vue"

import { PageHeadingTypes } from '@/types/PageHeading'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCodeCommit, faUsers, faGlobe, faGraduationCap, faMoneyBill, faPaperclip, faPaperPlane, faStickyNote, faTags, faCube, faCodeBranch, faShoppingCart, faHeart, faEnvelopeOpenText, faStopwatch } from '@fal'
import DummyComponent from '@/Components/DummyComponent.vue'
import TableEmailTrackingEvents from '@/Components/Tables/TableEmailTrackingEvents.vue'
import { capitalize } from 'lodash-es'
library.add( 
    faStopwatch,
    faStickyNote, faUsers, faGlobe, faMoneyBill, faGraduationCap, faTags, faCodeCommit, faPaperclip, faPaperPlane, faCube, faCodeBranch, faShoppingCart, faHeart )


const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    showcase?: {}
    email_tracking_events?: {}
}>()
let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        showcase: DummyComponent,
        email_tracking_events: TableEmailTrackingEvents,
    }

    return components[currentTab.value]
})


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"/>
</template>
