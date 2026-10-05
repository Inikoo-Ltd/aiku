<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 28 Nov 2024 16:45:01 Central Indonesia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import StatsBox from "@/Components/Stats/StatsBox.vue"
import StatsBoxNegativeList from "@/Components/Stats/StatsBoxNegativeList.vue"
import { StatsBoxTS } from "@/types/Components/StatsBox"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faNetworkWired, faHamsa, faExclamationTriangle, faClipboardCheck, faFolder, faFolderTree, faUnlink, faSeedling, faTags, faChartNetwork } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faNetworkWired, faHamsa, faExclamationTriangle, faClipboardCheck, faFolder, faFolderTree, faUnlink, faSeedling, faTags, faChartNetwork)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    stats: StatsBoxTS[]
    statsBoxNegative: StatsBoxTS[]
    statsBoxNegativeTitle: string
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <div class="mx-4 mt-4 flex flex-col gap-2">
        <span class="font-semibold">{{ ctrans('Crafts') }}</span>
        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5">
            <StatsBox v-for="(stat, index) in stats" :key="index" :stat="stat" />
        </dl>
    </div>

    <div class="mx-4 mt-6 flex flex-col gap-2">
        <span class="font-semibold">{{ statsBoxNegativeTitle }}</span>
        <div class="grid grid-cols-1 gap-2 lg:grid-cols-2">
            <StatsBoxNegativeList :stats="statsBoxNegative" />
        </div>
    </div>
</template>
