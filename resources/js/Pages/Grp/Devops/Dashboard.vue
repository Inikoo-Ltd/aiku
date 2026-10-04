
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
import { computed } from "vue"
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

const serverGroups = computed(() => props.servers.reduce<Record<string, ServerSummary[]>>((groups, server) => {
    (groups[server.group] ??= []).push(server)
    return groups
}, {}))

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4">
        <h3 class="mb-2 text-sm font-semibold">{{ ctrans("Servers") }}</h3>
        <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section v-for="(groupServers, group) in serverGroups" :key="group">
                <h4 class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans(group) }}</h4>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Link v-for="server in groupServers" :key="server.slug" :href="route('grp.devops.servers.show', [server.slug])" class="block hover:shadow">
                        <ServerUsageCard :server="server" :live="liveReadings[server.slug]" />
                    </Link>
                </div>
            </section>
        </div>
    </div>
</template>
