<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Mailshot } from "@/types/mailshot";
import icon from "@/Components/Icon.vue";
import { faSpellCheck, faSeedling, faPaperPlane, faStop, faCheckDouble, faCheck, faSkull, faDungeon, faEnvelopeOpen, faHandPointer, faEyeSlash, faInboxIn, faDumpsterFire, faThumbsDown, faFileExport, faExternalLink } from "@fal";
import { faSpinnerThird } from "@fad";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { RouteParams } from "@/types/route-params";
import { useFormatTime } from "@/Composables/useFormatTime";
import { inject, ref } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { trans } from "laravel-vue-i18n";
import axios from "axios";
import { notify } from "@kyvg/vue3-notification";

library.add(faSpellCheck, faSeedling, faPaperPlane, faStop, faCheckDouble, faCheck, faSkull, faDungeon, faEnvelopeOpen, faHandPointer, faEyeSlash, faInboxIn, faDumpsterFire, faThumbsDown, faFileExport, faExternalLink, faSpinnerThird);

defineProps<{
    data: object,
    tab?: string
}>();

const locale = inject("locale", aikuLocaleStructure);

const convertingMailshotId = ref<number | null>(null);

async function convertToPage(mailshot: Mailshot) {
    convertingMailshotId.value = mailshot.id;
    try {
        const response = await axios.get(
            route("grp.models.shop.mailshot.convert-to-blog", {
                shop: mailshot.shop_id,
                mailshot: mailshot.id
            }),
            { headers: { Accept: "application/json" } }
        );
        router.visit(route(response.data.route.name, response.data.route.parameters));
    } catch {
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to convert mailshot into a page"),
            type: "error"
        });
        convertingMailshotId.value = null;
    }
}

function webpageRoute(mailshot: Mailshot) {
    return route("grp.org.shops.show.web.blogs.show", {
        organisation: (route().params as RouteParams).organisation,
        shop: (route().params as RouteParams).shop,
        website: mailshot.webpage_website_slug,
        webpage: mailshot.webpage_slug
    });
}

function mailshotRoute(mailshot: Mailshot) {
    switch (route().current()) {
        case "grp.org.shops.show.marketing.mailshots.index":
            return route(
                "grp.org.shops.show.marketing.mailshots.show",
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
        case "grp.org.shops.show.crm.prospects.mailshots.index":
            return route(
                "grp.org.shops.show.crm.prospects.mailshots.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    mailshot.slug]);
        default:
            return '';
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(subject)="{ item: mailshot }">
            <div class="flex items-center gap-2">
                <Link v-if="mailshotRoute(mailshot)" :href="(mailshotRoute(mailshot) as string)" class="primaryLink">
                    {{ mailshot["subject"] }}
                </Link>
                <Link v-if="mailshot.state === 'sent' && mailshot.webpage_slug && !mailshot.has_source_reference && mailshot.type !== 'invite'" :href="webpageRoute(mailshot)"
                    v-tooltip="trans('Go to the webpage created from this mailshot')"
                    class="ml-2 inline-flex items-center px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded hover:bg-slate-200 hover:text-slate-800 transition">
                    <FontAwesomeIcon :icon="faExternalLink" class="mr-1" />
                    {{ trans("Go to webpage") }}
                </Link>
                <span v-else-if="mailshot.state === 'sent' && !mailshot.has_source_reference && mailshot.type !== 'invite'"
                    v-tooltip="trans('Convert this mailshot into a page')"
                    :class="[
                        'ml-2 inline-flex items-center px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600 rounded transition',
                        convertingMailshotId && convertingMailshotId !== mailshot.id
                            ? 'opacity-50 cursor-not-allowed pointer-events-none'
                            : 'hover:bg-slate-200 hover:text-slate-800 cursor-pointer'
                    ]"
                    @click="() => { convertToPage(mailshot); }">
                    <FontAwesomeIcon :icon="convertingMailshotId === mailshot.id ? faSpinnerThird : faFileExport" :spin="convertingMailshotId === mailshot.id" class="mr-1" />
                    {{ trans("Convert to Page") }}
                </span>
            </div>
        </template>
        <template #cell(state)="{ item: mailshot }">
            <div class="flex justify-center">
                <icon :data="mailshot.state_icon" />
            </div>
        </template>
        <template #cell(date)="{ item: mailshot }">
            <div class="text-sm">
                {{ useFormatTime(mailshot.date, { localeCode: locale.language.code, formatTime: "aiku" }) }}
            </div>
        </template>
    </Table>
</template>
