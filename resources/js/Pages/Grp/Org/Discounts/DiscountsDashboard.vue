<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, inject, ref } from 'vue'
import type { Component } from 'vue'

import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import SimpleBox from '@/Components/DataDisplay/SimpleBox.vue'



const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    stats: {
        label: string
        count: number
        icon: string
    }[]

    
}>()

const currentTab = ref(props.tabs.current)
const locale = inject('locale', aikuLocaleStructure)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components: Component = {
        dashboard: {}
    }

    return components[currentTab.value]

})

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <!-- <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" /> -->

    <!-- <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" /> -->
    <SimpleBox v-if="stats" :box_stats="stats" />

    <!-- <div class="flex gap-x-3 gap-y-4 p-4 flex-wrap">
        <div v-for="zzz in stats" class="bg-gray-50 min-w-64 border border-gray-300 rounded-md p-6">
            <div class="flex justify-between items-center mb-1">
                <div class="">{{ zzz.label }}</div>
                <FontAwesomeIcon :icon='zzz.icon' class=' text-xl text-gray-400' fixed-width aria-hidden='true' />
            </div>

            <div class="mb-1 text-2xl font-semibold">
                <CountUp
                    :endVal="zzz.count"
                    :duration="1.5"
                    :scrollSpyOnce="true"
                    :options="{
                        formattingFn: (value: number) => locale.number(value)
                    }"
                />
            </div>
        </div>        
    </div> -->
</template>