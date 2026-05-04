<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { Mailshot } from "@/types/mailshot"
import icon from '@/Components/Icon.vue'
import { faSpellCheck, faSeedling, faPaperPlane, faStop } from '@fal'
import { library } from "@fortawesome/fontawesome-svg-core"
import { useFormatTime } from '@/Composables/useFormatTime'
import Tag from '../Tag.vue'
library.add(faSpellCheck, faSeedling, faPaperPlane, faStop)

const props = defineProps<{
    data: object,
    tab?: string
}>()

// function emailBulkRunRoutes(emailBulkRun: {}) {
//     switch (route().current()) {
//         case 'grp.org.shops.show.dashboard.comms.outboxes.show':
//         return route(
//                 'grp.org.shops.show.dashboard.comms.outboxes.show.email-bulk-runs.show',
//                 [route().params['organisation'], route().params['shop'], route().params['outbox'], emailBulkRun.id])
//         default:
//             return null
//     }
// }

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- <template #cell(subject)="{ item: emailBulkRun }">
            <Link :href="emailBulkRunRoutes(emailBulkRun)" class="primaryLink">
                {{ emailBulkRun["subject"] }}
            </Link>
        </template> -->

        <template #cell(errors)="{ item }">
            <ul v-if="Array.isArray(item.errors) && item.errors.length" class="list-disc list-inside text-red-600 text-xs">
                <li v-for="(error, i) in item.errors" :key="i">{{ error }}</li>
            </ul>
        </template>

        <template #cell(status)="{ item }">
            <Tag :theme="item.status === 'completed' ? 4 : 7" :label="item.status">
                {{ item.status }}
            </Tag>
        </template>

        <template #cell(created_at)="{ item }">
            {{ useFormatTime(item.created_at) }}
        </template>
    </Table>
</template>
