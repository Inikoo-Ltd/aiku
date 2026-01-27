<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Table from "@/Components/Table/Table.vue"
import { Link } from '@inertiajs/vue3';
import { trans } from "laravel-vue-i18n"

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

function editRedirect(data: {}) {

    switch (route().current()) {
      case 'grp.org.shops.show.web.websites.show':
        return route(
            'grp.org.shops.show.web.websites.redirect.edit',
            {
                organisation: route().params['organisation'],
                shop: route().params['shop'],
                website: route().params['website'],
                redirect: data.id,
            }
        )
    }
}
// console.log('dddd', route())
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(to_webpage_url)="{ item: data }">
            <!-- <pre>{{ data }}</pre> -->
            <Link v-if="data.to_webpage_code" :href="webpageRoute(data)" class="primaryLink">
                {{ data['to_webpage_code'] }}
            </Link>
            <div v-else class="text-gray-400 italic">
                {{ trans("No target webpage") }}
            </div>
        </template>
        
        <template #cell(actions_from_website)="{ item: data }">
            <ButtonWithLink
                v-tooltip="trans('Edit redirect')"
                type="edit"
                :url="editRedirect(data)"
                size="sm"
            />
        </template>
    </Table>
</template>
