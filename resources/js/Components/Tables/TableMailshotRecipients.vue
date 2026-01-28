<!--
  - Author: eka yudinata (https://github.com/ekayudinata)
  - Created: Thursday, 8 Jan 2026 11:33:00 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, eka yudinata
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
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
    faVirus,
    faEnvelope,
    faBan
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
    faTimesCircle,
    faEnvelope,
    faBan
);

defineProps<{
    data: object,
    tab?: string
}>();

const locale = inject("locale", aikuLocaleStructure);

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: mailshotRecipient }">
            <Icon v-if="mailshotRecipient.state" :data="mailshotRecipient.state" />
        </template>

        <template #cell(email_address)="{ item: mailshotRecipient }">
            <div class="text-sm font-medium">
                {{ mailshotRecipient.email_address }}
            </div>
        </template>

        <template #cell(sent_at)="{ item: mailshotRecipient }">
            <div class="text-sm">
                {{ useFormatTime(mailshotRecipient.sent_at, { localeCode: locale.language.code, formatTime: "aiku" }) }}
            </div>
        </template>
    </Table>
</template>
