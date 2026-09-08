<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 28 Nov 2024 16:45:01 Central Indonesia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import StatsBoxNegativeList from "@/Components/Stats/StatsBoxNegativeList.vue";
import { StatsBoxTS } from "@/types/Components/StatsBox";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faNetworkWired, faHamsa, faExclamationTriangle, faClipboardCheck } from "@fal";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { capitalize } from "@/Composables/capitalize";
import { useLocaleStore } from "@/Stores/locale";
import { PageHeadingTypes } from "@/types/PageHeading";

library.add(faNetworkWired, faHamsa, faExclamationTriangle, faClipboardCheck);

const locale = useLocaleStore();

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    stats: {
        name: string
        stat: number
        color: string
        icon: string[]
        route: { name: string, parameters: object }
    }[]
    statsBoxNegative: StatsBoxTS[]
    statsBoxNegativeTitle: string
}>();

const iconColors: Record<string, string> = {
    indigo: "text-indigo-500",
    teal: "text-teal-500",
    amber: "text-amber-500",
    red: "text-red-500",
};
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <dl class="mx-4 mt-4 grid grid-cols-2 divide-x divide-gray-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100 md:max-w-xl">
        <Link
            v-for="card in stats"
            :key="card.name"
            :href="route(card.route.name, card.route.parameters)"
            class="flex items-center gap-2 px-3 py-2 text-xs hover:bg-gray-50">
            <FontAwesomeIcon :icon="card.icon" :class="iconColors[card.color] ?? 'text-gray-400'" fixed-width />
            <dt class="truncate text-gray-500">{{ card.name }}</dt>
            <dd class="ml-auto font-semibold tabular-nums text-gray-800">{{ locale.number(card.stat) }}</dd>
        </Link>
    </dl>

    <div class="mx-4 mt-6 flex flex-col gap-2">
        <span class="font-semibold">{{ statsBoxNegativeTitle }}</span>
        <div class="grid grid-cols-1 gap-2 lg:grid-cols-2">
            <StatsBoxNegativeList :stats="statsBoxNegative" />
        </div>
    </div>
</template>
