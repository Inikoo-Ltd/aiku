<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Mailshot } from "@/types/mailshot";
import icon from "@/Components/Icon.vue";
import { faSpellCheck, faSeedling, faPaperPlane, faStop, faCheckDouble, faCheck, faSkull, faDungeon, faEnvelopeOpen, faHandPointer, faEyeSlash, faInboxIn, faDumpsterFire, faThumbsDown , faHourglassStart} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { RouteParams } from "@/types/route-params";
import { useFormatTime } from "@/Composables/useFormatTime";
import { inject, ref } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

library.add(faSpellCheck, faSeedling, faPaperPlane, faStop, faCheckDouble, faCheck, faSkull, faDungeon, faEnvelopeOpen, faHandPointer, faEyeSlash, faInboxIn, faDumpsterFire, faThumbsDown, faCheck, faHourglassStart);

defineProps<{
    data: object,
    tab?: string
}>();


const locale = inject("locale", aikuLocaleStructure);

// TODO: Check and make sure this route
function mailshotRoute(mailshot: Mailshot) {
    // console.log(route().current())
    switch (route().current()) {
        case "grp.org.shops.show.marketing.templates.index":
            return route(
                "grp.org.shops.show.marketing.templates.workshop",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    mailshot.slug]);
        case "grp.org.shops.show.marketing.newsletters.index":
            return route(
                "grp.org.shops.show.marketing.newsletters.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    mailshot.slug]);
        case "grp.org.shops.show.web.websites.outboxes.show":
            return route(
                "grp.org.shops.show.web.websites.outboxes.mailshots.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).website,
                    (route().params as RouteParams).outbox,
                    mailshot.slug]);
        default:
            return '';
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: mailshot }">
            <div>
                <Link v-if="mailshotRoute(mailshot)" :href="(mailshotRoute(mailshot) as string)" class="primaryLink">
                    {{ mailshot["name"] }}
                </Link>

                <span
                    v-tooltip="mailshot.has_compiled_layout ? null : ctrans('Please save the email template by clicking the SAVE button in the BeeFree workspace before it can be used')"
                    class="ml-2 inline-flex items-center px-2 py-0.5 text-xs font-medium text-slate-600 rounded hover:bg-slate-200 cursor-pointer transition">
                    <FontAwesomeIcon :icon="mailshot.has_compiled_layout ? faCheck : faHourglassStart" class="mr-1"
                        :style="{ color: mailshot.has_compiled_layout ? '#22c55e' : '#f59e0b' }" />
                    {{ mailshot.has_compiled_layout ? ctrans("Ready") : ctrans("Temporary Save") }}
                </span>
            </div>
        </template>
        <template #cell(state)="{ item: mailshot }">
            <div class="flex justify-center">
                <icon :data="mailshot.state_icon" />
            </div>
        </template>
        <template #cell(created_at)="{ item: mailshot }">
            <div class="text-sm">
                {{ useFormatTime(mailshot.created_at, { localeCode: locale.language.code, formatTime: "aiku" }) }}
            </div>
        </template>
    </Table>
</template>
