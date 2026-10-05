<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHistory, faUndo } from "@fal"

library.add(faHistory, faUndo)

defineProps<{
    pageHead: any
    title: string
    data: any
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <Table :resource="data" class="mt-2">
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.created_at, { formatTime: 'hms', keepTimezone: true }) }}</span>
        </template>

        <template #cell(username)="{ item }">
            <span class="font-medium">{{ item.username ?? '-' }}</span>
        </template>

        <template #cell(label)="{ item }">
            <div>{{ item.label }}</div>
            <div v-if="item.shops.length > 1" class="text-xs text-gray-500">{{ ctrans("Shops") }}: {{ item.shops.join(', ') }}</div>
        </template>

        <template #cell(request_text)="{ item }">
            <span class="text-gray-500 italic">{{ item.request_text ?? '-' }}</span>
        </template>

        <template #cell(before_after)="{ item }">
            <div class="text-xs">
                <div class="text-gray-500 line-through">{{ item.before_text }}</div>
                <div>{{ item.after_text }}</div>
            </div>
        </template>

        <template #cell(reverted_at)="{ item }">
            <span v-if="item.reverted_at" class="text-xs text-gray-500 whitespace-nowrap">
                {{ ctrans("Reverted by :user", { user: item.reverted_by_username ?? '-' }) }}
                {{ useFormatTime(item.reverted_at, { formatTime: 'hm' }) }}
            </span>
            <ModalConfirmation
                v-else-if="item.can_revert"
                :routeYes="item.revert_route"
                :title="ctrans('Revert this change?')"
                :description="ctrans('It goes back to: :before', { before: item.before_text ?? '-' })"
                :yesLabel="ctrans('Revert')"
                :noLabel="ctrans('Cancel')"
                :body="{}"
            >
                <template #default="{ changeModel }">
                    <Button type="tertiary" size="xs" icon="fal fa-undo" :label="ctrans('Revert')" @click="changeModel" />
                </template>
            </ModalConfirmation>
            <span v-else class="text-xs text-green-600">{{ ctrans("Live") }}</span>
        </template>
    </Table>
</template>
