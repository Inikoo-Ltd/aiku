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

const props = defineProps<{
    data: object,
    tab?: string
}>();

const locale = inject("locale", aikuLocaleStructure);

const dummyData = {
    "data": [
        {
            "id": 6,
            "recipient_type": null,
            "state": {
                "tooltip": "sent",
                "icon": "fal fa-paper-plane",
                "class": "text-green-600"
            },
            "email_address": "matei_bogdan65@yahoo.com",
            "sent_at": "2026-01-15 09:11:46+08",
            "customer_name": "Bogdan Matei"
        }
    ],
    "links": {
        "first": "https://app.aiku.test/org/sk/shops/dssk/marketing/newsletters/news-dssk-2026-01-15?tab=recipients&recipientsPage=1",
        "last": "https://app.aiku.test/org/sk/shops/dssk/marketing/newsletters/news-dssk-2026-01-15?tab=recipients&recipientsPage=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "links": [
            {
                "url": null,
                "label": "&laquo; Previous",
                "page": null,
                "active": false
            },
            {
                "url": "https://app.aiku.test/org/sk/shops/dssk/marketing/newsletters/news-dssk-2026-01-15?tab=recipients&recipientsPage=1",
                "label": "1",
                "page": 1,
                "active": true
            },
            {
                "url": null,
                "label": "Next &raquo;",
                "page": null,
                "active": false
            }
        ],
        "path": "https://app.aiku.test/org/sk/shops/dssk/marketing/newsletters/news-dssk-2026-01-15",
        "per_page": 50,
        "to": 1,
        "total": 1
    }
}

console.log("datas", dummyData);
console.log("tab", props.tab)
</script>

<template>
    <Table :resource="dummyData" :name="tab" class="mt-5">
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
