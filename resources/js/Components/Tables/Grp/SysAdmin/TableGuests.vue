<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import Icon from '@/Components/Icon.vue';
import { Guest } from "@/types/guest";

const props = defineProps<{
    data: object,
    tab?:string,
}>()


function guestRoute(guest: Guest) {
    switch (route().current()) {
        case 'grp.sysadmin.guests.index':
        case 'grp.sysadmin.guests.inactive.index':
        case 'grp.sysadmin.guests.all.index':
        case 'grp.org.hr.job_positions.show':
            return route(
                'grp.sysadmin.guests.show',
                [guest.slug]);

    }
}

</script>

<template>
    <Table :resource="data" :name="tab"  class="mt-5">
        <template #cell(status)="{ item: guest }">
            <Icon :data="guest['status']" />
        </template>

        <template #cell(code)="{ item: guest }">
            <Link v-if="guestRoute(guest)" :href="guestRoute(guest)" class="primaryLink">
                {{ guest['code'] }}
            </Link>
            <span v-else>{{ guest['code'] }}</span>
        </template>

        <template #cell(share)="{ item: guest }">
            <span v-if="guest['share']">{{ guest['share'] }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>
    </Table>
</template>
