<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 20:33:56 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableClockingMachines from "@/Components/Tables/Grp/Org/HumanResources/TableClockingMachines.vue";
import ModalCreateClockingMachine from "@/Components/HumanResources/ModalCreateClockingMachine.vue";
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, ref } from "vue"
import { PageHeadingTypes } from "@/types/PageHeading";
import type { Navigation } from "@/types/Tabs"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlug, faUnlink } from '@fal'

library.add(faPlug, faUnlink)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: Navigation
    }
    connected?: object
    disconnected?: object
    createClockingMachine?: {
        route: {
            name: string
            parameters: Record<string, string | number>
        }
        workplaces: {
            value: number
            label: string
        }[]
    }
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const currentData = computed(() => props[currentTab.value as keyof typeof props])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template v-if="createClockingMachine" #otherBefore>
            <ModalCreateClockingMachine
                :route="createClockingMachine.route"
                :workplaces="createClockingMachine.workplaces"
            />
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <TableClockingMachines :data="currentData" :tab="currentTab" />
</template>
