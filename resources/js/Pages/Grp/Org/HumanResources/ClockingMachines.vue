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
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading";

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
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
    <TableClockingMachines :data="data" />
</template>
