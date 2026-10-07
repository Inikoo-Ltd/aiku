<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { Organisation } from "@/types/organisation"
import Icon from "@/Components/Icon.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'

import { library } from "@fortawesome/fontawesome-svg-core"
import { faStore, faAd, faExternalLink } from '@fal'
library.add(faStore, faAd, faExternalLink)


const props = defineProps<{
    data: {}
    tab?: string
}>()

function orgRoute(org: Organisation) {
    switch (route().current()) {
        case 'grp.organisations.index':
            return route('grp.org.dashboard.show', [org.slug])

    }
}

</script>

<template>
    <!-- <pre>{{ org }}</pre> -->

    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: org }">
            <Link :href="orgRoute(org)" class="primaryLink">
                {{ org['slug'] }}
            </Link>
        </template>

        <template #cell(type)="{ item: org }">
            <Icon :data="org['type_icon']" />
        </template>

        <template #cell(action)="{ item: org}">
            <Link :href="route('grp.org.dashboard.show', org.slug)">
                <Button label="Dashboard" :style="'tertiary'" iconRight="fal fa-external-link" size="s" />
            </Link>
        </template>
    </Table>
</template>
