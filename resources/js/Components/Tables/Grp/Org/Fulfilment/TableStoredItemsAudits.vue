<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 17 May 2024 13:09:02 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import StoredItemProperty from '@/Components/StoredItemsProperty.vue'
import type { Meta, Links } from "@/types/Table"
import { Pallet } from "@/types/Pallet"
import { useFormatTime } from '@/Composables/useFormatTime'
import Button from "@/Components/Elements/Buttons/Button.vue"
import Popover from '@/Components/Popover.vue'
import { Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrashAlt } from "@far"
import { faCheckCircle } from "@fas"
import { faSeedling, faCheck, faSignOutAlt, faSpellCheck, faTimes, faCheckDouble, faCross, faFragile, faGhost, faBoxUp, faStickyNote, } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

import { routeType } from "@/types/route"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Tag from "@/Components/Tag.vue"

library.add(faSeedling, faTrashAlt, faSignOutAlt, faSpellCheck, faCheck, faTimes, faCheckDouble, faCross, faFragile, faGhost, faBoxUp, faStickyNote, faCheckCircle)


const props = defineProps<{
    data: {},
    tab?: string
}>()

function storedItemAuditRoute(storedItemAudit: {}) {
    switch (route().current()) {
        case "grp.org.fulfilments.show.crm.customers.show.stored-items.index":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.stored-item-audits.show",
                [route().params["organisation"], route().params["fulfilment"], route().params["fulfilmentCustomer"], storedItemAudit.slug])
        case "retina.fulfilment.storage.stored-items-audits.index":
            return route(
                "retina.fulfilment.storage.stored-items-audits.show",
                [storedItemAudit.slug])
        default:
            return ''
    }
}
</script>



<template>
    <!-- <pre>{{ props.data.data[0] }}</pre> -->
    <Table :resource="data" :name="tab" class="mt-5">
        {{ data }}
        <template #cell(state)="{ item: storedItemAudit }">
            <Icon :data="storedItemAudit['state_icon']" class="px-1" />
        </template>

        <template #cell(reference)="{ item: storedItemAudit }">
            <Link :href="storedItemAuditRoute(storedItemAudit)" class="primaryLink">
                {{ storedItemAudit.reference }}
            </Link>
        </template>

        <template #cell(created_at)="{ item: storedItemAudit }">
            {{ useFormatTime(storedItemAudit.created_at, {formatTime: 'hm'}) }}
        </template>
    </Table>
</template>