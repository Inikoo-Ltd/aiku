<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

defineProps<{
    data: {}
}>()
</script>

<template>
    <Table :resource="data" class="mt-5">
        <!-- Column: Session ID -->
        <template #cell(session_id)="{ item: visitor }">
            <span class="font-mono text-xs">{{ visitor.session_id }}</span>
        </template>

        <!-- Column: Device Type -->
        <template #cell(device_type)="{ item: visitor }">
            <div class="flex items-center gap-2">
                <FontAwesomeIcon 
                    v-if="visitor.device_type.icon" 
                    :icon="visitor.device_type.icon" 
                    :title="visitor.device_type.tooltip"
                    class="text-gray-600"
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
                    class="text-gray-600"
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
                    class="text-gray-600"
                />
                <span>{{ visitor.os.label }}</span>
            </div>
        </template>

        <!-- Column: Location -->
        <template #cell(location)="{ item: visitor }">
            <AddressLocation :data="visitor.location" />
        </template>

        <!-- Column: Page Views -->
        <template #cell(page_views)="{ item: visitor }">
            <span class="font-medium">{{ visitor.page_views }}</span>
        </template>

        <!-- Column: Duration -->
        <template #cell(duration)="{ item: visitor }">
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
