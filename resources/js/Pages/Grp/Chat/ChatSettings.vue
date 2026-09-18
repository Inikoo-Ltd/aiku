<script setup lang="ts">
import { ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Table from "@/Components/Table/Table.vue"
import AgentsTable from "@/Components/Chat/AgentsTable.vue"
import Icon from "@/Components/Icon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink } from "@fal"

library.add(faExternalLink)

const props = defineProps<{
    title: string
    pageHead: any
    tabs: {
        current: string
        navigation: any
    }
    can_manage_agents: boolean
    agents?: any
    whatsapp_templates?: any
}>()

const currentTab = ref(props.tabs.current)

const handleTabUpdate = (tabSlug: string) => {
    currentTab.value = tabSlug
    router.get(route("grp.chat.settings"), { tab: tabSlug }, {
        preserveScroll: true,
        preserveState: true,
        only: [tabSlug],
    })
}

const shopTemplatesRoute = (shop: { organisation_slug: string; slug: string }) =>
    route("grp.org.shops.show.chat.whatsapp_templates.index", [shop.organisation_slug, shop.slug])
</script>

<template>
    <Head :title="trans('Chat settings')" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <AgentsTable
        v-if="currentTab === 'agents'"
        :data="agents"
        name="agents"
        :canManage="can_manage_agents" />

    <Table
        v-else-if="currentTab === 'whatsapp_templates'"
        :resource="whatsapp_templates"
        name="whatsapp_templates">
        <template #cell(shop)="{ item }">
            <Link
                v-if="item.shop"
                :href="shopTemplatesRoute(item.shop)"
                class="primaryLink inline-flex items-center gap-1.5 text-sm">
                {{ item.shop.name }}
                <FontAwesomeIcon :icon="['fal', 'external-link']" class="text-[10px]" aria-hidden="true" />
            </Link>
            <span v-else class="text-sm text-gray-400">-</span>
        </template>

        <template #cell(status)="{ item }">
            <span
                class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium"
                :class="{
                    'bg-green-100 text-green-800': item.status === 'APPROVED',
                    'bg-amber-100 text-amber-800': item.status === 'PENDING',
                    'bg-red-100 text-red-800': item.status === 'REJECTED',
                    'bg-gray-100 text-gray-700': !['APPROVED', 'PENDING', 'REJECTED'].includes(item.status),
                }">
                {{ item.is_draft ? trans("Draft") : item.status }}
            </span>
        </template>

        <template #cell(variables)="{ item }">
            <span v-if="!item.variable_count" class="text-sm text-gray-400">—</span>
            <span
                v-else-if="item.merge_tags?.length"
                class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                {{ trans("Auto-filled") }}
            </span>
            <span v-else class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                {{ trans(":count to map", { count: item.variable_count }) }}
            </span>
        </template>

        <template #cell(actions)="{ item }">
            <Link
                v-if="item.shop"
                :href="shopTemplatesRoute(item.shop)"
                class="primaryLink text-xs">
                {{ trans("Open in shop") }}
            </Link>
        </template>
    </Table>
</template>
