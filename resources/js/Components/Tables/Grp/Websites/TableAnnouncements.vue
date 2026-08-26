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
import { ctrans } from '@/Composables/useTrans'
import { faStop } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faStop)

const locale = useLocaleStore()

const props = defineProps<{
    data: {}
    tab?: string
}>()

const positionLabel = (position: string) => ({
    'top-bar': ctrans('Top bar'),
    'bottom-menu': ctrans('Below the Menu'),
    'top-footer': ctrans('Top footer'),
}[position] ?? position)

function announcementRoute(announcement) {
    return route(
        'grp.org.shops.show.web.announcements.show',
        {
            ...route().params,
            announcement: announcement.ulid,
        })
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
            <Link :href="announcementRoute(announcement)" :id="announcement['ulid']" class="primaryLink py-1 px-2 whitespace-nowrap">
                {{announcement['name']}}
            </Link>
        </template>

        <template #cell(position)="{ item: announcement }">
            {{ positionLabel(announcement.position) }}
        </template>

        <template #cell(closed_at)="{ item: announcement }">
            <div class="flex items-center justify-end gap-x-2">
                <!-- <Tag :label="" :theme="7" noHoverColor /> -->
                <span v-if="announcement.closed_at" class="whitespace-nowrap">
                    {{ useFormatTime(announcement.closed_at, {formatTime: 'hm'}) }}
                </span>
                <span v-else>
                    -
                </span>
            </div>
            <div v-if="announcement.is_expired" class="inline-flex text-xxs items-center gap-x-1 rounded select-none px-1 py-0.5 w-fit font-medium bg-red-100 border border-red-200 text-red-500">
                {{ ctrans('Expired') }}
            </div>
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
