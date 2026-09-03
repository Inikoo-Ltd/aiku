<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketThread from "@/Components/Tickets/TicketThread.vue"
import { Select } from "primevue"

const props = defineProps<{
    pageHead: any
    title: string
    ticket: any
    comments: any[]
    options: {
        statuses: { label: string; value: string }[]
        priorities: { label: string; value: string }[]
        assignees: { label: string; value: number }[]
    }
    routes: {
        update: { name: string; parameters: Record<string, unknown> }
        comment: { name: string; parameters: Record<string, unknown> }
    }
}>()

const update = (field: string, value: unknown) => {
    router.patch(route(props.routes.update.name, props.routes.update.parameters), { [field]: value }, { preserveScroll: true })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-lg font-semibold">{{ ticket.subject }}</h2>
            <TicketThread :ticket="ticket" :comments="comments" :comment-route="routes.comment" allow-internal />
        </div>
        <aside class="bg-white rounded-lg border border-gray-300 p-4 space-y-4 text-sm self-start">
            <div>
                <p class="text-xs text-gray-500 mb-1">{{ trans("Status") }}</p>
                <Select :model-value="ticket.status" :options="options.statuses" option-label="label" option-value="value" class="w-full" @update:model-value="update('status', $event)" />
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">{{ trans("Priority") }}</p>
                <Select :model-value="ticket.priority" :options="options.priorities" option-label="label" option-value="value" class="w-full" @update:model-value="update('priority', $event)" />
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">{{ trans("Assignee") }}</p>
                <Select :model-value="ticket.assignee_id" :options="options.assignees" option-label="label" option-value="value" show-clear filter class="w-full" :placeholder="trans('Unassigned')" @update:model-value="update('assignee_id', $event)" />
            </div>
            <dl class="space-y-1 text-gray-600">
                <div class="flex justify-between"><dt>{{ trans("Type") }}</dt><dd>{{ ticket.type }}</dd></div>
                <div class="flex justify-between"><dt>{{ trans("Reporter") }}</dt><dd>{{ ticket.reporter || "-" }}</dd></div>
                <div v-if="ticket.customer" class="flex justify-between"><dt>{{ trans("Customer") }}</dt><dd>{{ ticket.customer }}</dd></div>
                <div v-if="ticket.shop" class="flex justify-between"><dt>{{ trans("Shop") }}</dt><dd>{{ ticket.shop }}</dd></div>
            </dl>
        </aside>
    </div>
</template>
