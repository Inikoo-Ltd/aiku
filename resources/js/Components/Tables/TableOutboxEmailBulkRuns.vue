<!--
  - Author: Eka Yudinata <ekayudinatha@gmail.com>
  - Created: Mon, 01 Dec 2025 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Eka Yudinata
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { EmailBulkRun } from "@/types/email-bulk-runs";
import {
    faCheck,
    faDumpster,
    faEnvelopeOpen,
    faExclamationCircle,
    faExclamationTriangle,
    faHandPaper,
    faInboxIn,
    faMousePointer,
    faPaperPlane,
    faSpellCheck,
    faSquare,
    faTimesCircle,
    faVirus
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import Icon from "../Icon.vue";
import { inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { useFormatTime } from "@/Composables/useFormatTime";
import { RouteParams } from "@/types/route-params";

library.add(
    faSpellCheck,
    faPaperPlane,
    faExclamationCircle,
    faVirus,
    faInboxIn,
    faMousePointer,
    faExclamationTriangle,
    faSquare,
    faEnvelopeOpen,
    faMousePointer,
    faDumpster,
    faHandPaper,
    faCheck,
    faTimesCircle
);
defineProps<{
    data: object,
    tab?: string
}>();


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
         <template #cell(state)="{ item: emailBulkRun }">
            <Icon v-if="emailBulkRun.state" :data="emailBulkRun.state" />
            <Icon v-if="emailBulkRun.state_icon" :data="emailBulkRun.state_icon" />
        </template>
    </Table>
</template>


