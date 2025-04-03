<script setup lang="ts">
import PageHeading from '@/Components/Headings/PageHeading.vue';
import Tabs from '@/Components/Navigation/Tabs.vue';
import CustomerShowcase from '@/Components/Showcases/Grp/CustomerShowcase.vue';
import { useTabChange } from '@/Composables/tab-change';
import { PageHeading as PageHeadingTS } from '@/types/PageHeading'
import { computed } from 'vue';
import { ref } from 'vue';
import type { Component } from 'vue'

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

const component = computed(() => {
    const components: Component = {
        showcase: CustomerShowcase,
      
    }

    return components[currentTab.value]
})
</script>

<template>
	<PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"  />
</template>
