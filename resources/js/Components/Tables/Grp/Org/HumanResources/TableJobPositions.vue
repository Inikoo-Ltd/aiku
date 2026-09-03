<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 12 May 2024 15:26:39 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import {JobPosition} from "@/types/job-position";

const props = defineProps<{
    data: object,
    tab?:string
}>()

function jobPositionRoute(jobPosition: JobPosition) {
    switch (route().current()) {
        case 'grp.org.hr.job_positions.index':
        case 'grp.org.hr.employees.show.positions.index':
            return route(
                'grp.org.hr.job_positions.show',
                [
                    route().params['organisation'],
                    jobPosition.slug
                ]);
        case 'grp.overview.hr.responsibilities.index':
            // Group scoped responsibilities belong to no organisation, so they have no show page
            if (!jobPosition.organisation_slug) return undefined
            return route(
                'grp.org.hr.job_positions.show',
                [
                    jobPosition.organisation_slug,
                    jobPosition.slug
                ]);

    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: jobPosition }">
            <Link v-if="jobPositionRoute(jobPosition)" :href="jobPositionRoute(jobPosition)" class="primaryLink">
                {{ jobPosition['code'] }}
            </Link>
            <span v-else>{{ jobPosition['code'] }}</span>
        </template>

        <template #cell(share)="{ item: jobPosition }">
            <span v-if="jobPosition['share']">{{ jobPosition['share'] }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>
    </Table>
</template>
