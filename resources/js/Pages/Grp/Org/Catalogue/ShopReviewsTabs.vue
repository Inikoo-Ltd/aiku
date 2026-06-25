<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableReviews from "@/Components/Shop/Reviews/TableReviews.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    shop_id: number
    tabs: {
        current: string
        navigation: Record<string, any>
    }
    products?: Record<string, any>
    families?: Record<string, any>
    shop?: Record<string, any>
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const currentData = computed(() => (props as Record<string, any>)[currentTab.value])
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead" />

    <Tabs
        :current="currentTab"
        :navigation="props.tabs.navigation"
        @update:tab="handleTabUpdate"
    />

    <TableReviews
        v-if="currentData"
        :key="currentTab"
        :data="currentData"
        :tab="currentTab"
    />
</template>
