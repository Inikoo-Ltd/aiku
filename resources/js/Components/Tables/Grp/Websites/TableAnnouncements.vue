<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 13 Jul 2023 22:20:34 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import {useLocaleStore} from '@/Stores/locale'

import {Link} from "@inertiajs/vue3"
import Tag from '@/Components/Tag.vue'
import Icon from '@/Components/Icon.vue'
import { useFormatTime } from '@/Composables/useFormatTime';
import { faStop } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faStop)

const locale = useLocaleStore()

const props = defineProps<{
    data: {}
    tab?: string
}>()

function announcementRoute(announcement) {
    return route(
        'customer.portfolio.websites.announcements.show',
        [route().params['portfolioWebsite'], announcement.ulid]);
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(status)="{ item: announcement }">
            <Icon :data="announcement.status" />
        </template>

        <template #cell(created_at)="{ item: announcement }">
            {{ useFormatTime(announcement.created_at, {formatTime: 'hh:mm EEE, do MMM yy'})}}
        </template>

        <template #cell(name)="{ item: announcement }">
            <Link :href="announcementRoute(announcement)" :id="announcement['ulid']" class="specialUnderlineCustomer py-1 px-2 whitespace-nowrap">
                {{announcement['name']}}
            </Link>
        </template>

        <template #cell(show_pages)="{ item: announcement }">
            <div class="flex flex-wrap gap-x-1 gap-y-1">
                <template v-for="page in announcement.show_pages">
                    <Tag :label="page" noHoverColor />
                </template>
            </div>
        </template>

        <template #cell(hide_pages)="{ item: announcement }">
            <div class="flex flex-wrap gap-x-1 gap-y-1">
                <template v-for="page in announcement.hide_pages">
                    <Tag :label="page" noHoverColor />
                </template>
            </div>
        </template>

    </Table>
</template>
