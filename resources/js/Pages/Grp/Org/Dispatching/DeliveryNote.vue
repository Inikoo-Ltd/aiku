<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faChair, faFolder, } from '@fal'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import AlertMessage from '@/Components/Utils/AlertMessage.vue'
import BoxNote from '@/Components/Pallet/BoxNote.vue'
import Timeline from '@/Components/Utils/Timeline.vue'
import { Timeline as TSTimeline } from '@/types/Timeline'
import { computed, ref } from 'vue'
import type { Component } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import BoxStatsDeliveryNote from '@/Components/Warehouse/DeliveryNotes/BoxStatsDeliveryNote.vue'
import TableSKOSOrdered from '@/Components/Warehouse/DeliveryNotes/TableSKOSOrdered.vue'
import TablePickings from '@/Components/Warehouse/DeliveryNotes/TablePickings.vue'
import { routeType } from '@/types/route'
import Tabs from '@/Components/Navigation/Tabs.vue'
import type { DeliveryNote } from '@/types/warehouse'


library.add(faFolder, faCube, faChair)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    items?: {}
    pickings: {}
    alert?: {
        status: string
        title?: string
        description?: string
    }
    delivery_note: DeliveryNote
    notes: {
        note_list: {
            label: string
            note: string
            editable?: boolean
            bgColor?: string
            textColor?: string
            color?: string
            lockMessage?: string
            field: string  // customer_notes, public_notes, internal_notes
        }[]
        // updateRoute: routeType
    }
    timelines: {
        [key: string]: TSTimeline
    }
    box_stats: {}
    routes: {
        update: routeType
        products_list: routeType
        pickers_list: routeType
        packers_list: routeType
    }
}>()

const currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const component = computed(() => {
    const components: Component = {
        items: TableSKOSOrdered,
        pickings: TablePickings,
    }

    return components[currentTab.value]
})

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <!-- <template #button-action-picked="{ action }">
            {{action}}
        </template> -->
    </PageHeading>

    <!-- Section: Pallet Warning -->
    <div v-if="alert?.status" class="p-2 pb-0">
        <AlertMessage :alert />
    </div>
    
    <!-- Section: Box Note -->
    <div class="relative">
        <Transition name="headlessui">
            <div v-if="notes?.note_list?.some(item => !!(item?.note?.trim()))" class="p-2 grid sm:grid-cols-3 gap-y-2 gap-x-2 h-fit lg:max-h-64 w-full lg:justify-center border-b border-gray-300">
                <BoxNote
                    v-for="(note, index) in notes.note_list"
                    :key="index+note.label"
                    :noteData="note"
                    :updateRoute="routes.update"
                />
            </div>
        </Transition>
    </div>

    <!-- Section: Timeline -->
    <div class="mt-4 sm:mt-1 border-b border-gray-200 pb-2">
        <Timeline
            v-if="timelines"
            :options="timelines"
            :state="delivery_note.state"
            :slidesPerView="6"
        />
    </div>

    <BoxStatsDeliveryNote v-if="box_stats" :boxStats="box_stats" :routes/>

    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

    <div class="pb-12">
        <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" :routes :state="delivery_note.state" />
    </div>

    <!-- <pre>{{ props }}</pre> -->
</template>
