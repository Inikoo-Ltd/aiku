<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Table from '@/Components/Table/Table.vue'
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"

defineProps<{
    data: {}
    tab?: string
}>()

const routeParams = route().params as Record<string, string>

const pageViewsOfVisitorHref = (visitorId: number) => routeParams.shop
    ? route("grp.org.shops.show.seo.page_views.visitor", [routeParams.organisation, routeParams.shop, visitorId])
    : null

const webUserHref = (webUser: { slug: string | null, customer_slug: string | null }) => routeParams.shop && webUser.slug && webUser.customer_slug
    ? route("grp.org.shops.show.crm.customers.show.web_users.show", [routeParams.organisation, routeParams.shop, webUser.customer_slug, webUser.slug])
    : null
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Session ID -->
        <template #cell(session_id)="{ item: visitor }">
            <Link v-if="pageViewsOfVisitorHref(visitor.id)" :href="pageViewsOfVisitorHref(visitor.id)" class="primaryLink font-mono text-xs">
                {{ visitor.session_id }}
            </Link>
            <span v-else class="font-mono text-xs">{{ visitor.session_id }}</span>
        </template>

        <template #cell(web_user)="{ item: visitor }">
            <template v-if="visitor.web_user">
                <Link v-if="webUserHref(visitor.web_user)" :href="webUserHref(visitor.web_user)" class="primaryLink">
                    {{ visitor.web_user.contact_name }}
                </Link>
                <span v-else>{{ visitor.web_user.contact_name }}</span>
            </template>
            <span v-else class="text-gray-400">{{ ctrans("Guest") }}</span>
        </template>

        <!-- Column: Device Type -->
        <template #cell(device_type)="{ item: visitor }">
            <div class="flex items-center gap-2">
                <FontAwesomeIcon 
                    v-if="visitor.device_type.icon" 
                    :icon="visitor.device_type.icon" 
                    :title="visitor.device_type.tooltip"
                    class="text-gray-600" fixed-width
                />
                <span>{{ visitor.device_type.label }}</span>
            </div>
        </template>

        <!-- Column: Browser -->
        <template #cell(browser)="{ item: visitor }">
            <div class="flex items-center gap-2">
                <FontAwesomeIcon 
                    v-if="visitor.browser.icon" 
                    :icon="visitor.browser.icon" 
                    :title="visitor.browser.tooltip"
                    class="text-gray-600" fixed-width
                />
                <span>{{ visitor.browser.label }}</span>
            </div>
        </template>

        <!-- Column: OS -->
        <template #cell(os)="{ item: visitor }">
            <div class="flex items-center gap-2">
                <FontAwesomeIcon 
                    v-if="visitor.os.icon" 
                    :icon="visitor.os.icon" 
                    :title="visitor.os.tooltip"
                    class="text-gray-600" fixed-width
                />
                <span>{{ visitor.os.label }}</span>
            </div>
        </template>

        <!-- Column: Location -->
        <template #cell(location)="{ item: visitor }">
            <AddressLocation :data="visitor.location" />
        </template>

        <template #cell(traffic_source_type)="{ item: visitor }">
            <span
                v-if="visitor.traffic_source_type"
                v-tooltip="visitor.traffic_source_type.reference"
                :class="visitor.traffic_source_type.reference ? 'cursor-help underline decoration-dotted decoration-gray-400 underline-offset-4' : ''"
            >
                {{ visitor.traffic_source_type.label }}
            </span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <!-- Column: Page Views -->
        <template #cell(page_views)="{ item: visitor }">
            <span class="font-medium">{{ visitor.page_views }}</span>
        </template>

        <!-- Column: Duration -->
        <template #cell(duration_seconds)="{ item: visitor }">
            <span>{{ visitor.duration }}</span>
        </template>

        <!-- Column: Bounce -->
        <template #cell(bounce)="{ item: visitor }">
            <span :class="visitor.bounce === 'Yes' ? 'text-red-600' : 'text-green-600'">
                {{ visitor.bounce }}
            </span>
        </template>

        <!-- Column: First Seen -->
        <template #cell(first_seen_at)="{ item: visitor }">
            <span class="text-sm text-gray-600">{{ visitor.first_seen_at }}</span>
        </template>

        <!-- Column: Last Seen -->
        <template #cell(last_seen_at)="{ item: visitor }">
            <span class="text-sm text-gray-600">{{ visitor.last_seen_at }}</span>
        </template>
    </Table>
</template>
