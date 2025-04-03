<script setup lang="ts">
import PageHeading from '@/Components/Headings/PageHeading.vue';
import Tabs from '@/Components/Navigation/Tabs.vue';
import { useTabChange } from '@/Composables/tab-change';
import { PageHeading as PageHeadingTS } from '@/types/PageHeading'
import { ref } from 'vue';

const props = defineProps<{
    title: string
    pageHead: PageHeadingTS
    tabs: {
        current: string
        navigation: {}
    }
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

</script>

<template>
	<PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
</template>
