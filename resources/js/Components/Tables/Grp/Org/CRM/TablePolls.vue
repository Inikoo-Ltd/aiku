<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"


const props = defineProps<{
    data: {}
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item }">
            <Link :href="route('grp.org.shops.show.crm.polls.show', { organisation: route().params.organisation, shop: route().params.shop, poll: item.slug })" class="primaryLink">
                {{ item.name }}
            </Link>
        </template>

        <template #cell(in_registration)="{ item }">
            <div v-if="item.in_registration" v-tooltip="trans('Will show in registration')" class="text-center text-green-500">
                <FontAwesomeIcon icon="fal fa-check" class="" fixed-width aria-hidden="true" />
            </div>
            <div v-else>

            </div>
        </template>
    </Table>
</template>
