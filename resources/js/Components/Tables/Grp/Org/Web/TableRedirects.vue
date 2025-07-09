<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { Link } from '@inertiajs/vue3';

defineProps<{
    data: {}
    tab?: string
}>()

function webpageRoute(data: {}) {

    switch (route().current()) {
      case 'grp.org.shops.show.web.websites.show':
        return route(
            'grp.org.shops.show.web.webpages.show',
            [
                route().params['organisation'],
                route().params['shop'],
                route().params['website'],
                data.to_webpage_slug
            ]);
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
      <template #cell(to_webpage_url)="{ item: data }">
            <Link :href="webpageRoute(data)" class="primaryLink">
              {{ data['to_webpage_code'] }}
            </Link>
        </template>
    </Table>
</template>
