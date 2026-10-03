
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 06 Jun 2026 09:22:41 Indochina Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { faDatabase, faServer } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ServerUsageCard, { ServerSummary } from "@/Components/DevOps/ServerUsageCard.vue"
import { LiveServerReading, useLiveServerMetrics } from "@/Composables/useLiveServerMetrics"

library.add(faDatabase, faServer)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    servers: ServerSummary[]
    liveReadings: Record<string, LiveServerReading[]>
}>()

const { readings: liveReadings } = useLiveServerMetrics(props.liveReadings)

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4">
        <h3 class="mb-2 text-sm font-semibold">{{ ctrans("Servers") }}</h3>
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link v-for="server in servers" :key="server.slug" :href="route('grp.devops.servers.show', [server.slug])" class="block hover:shadow">
                <ServerUsageCard :server="server" :live="liveReadings[server.slug]" />
            </Link>
        </div>
    </div>
</template>
