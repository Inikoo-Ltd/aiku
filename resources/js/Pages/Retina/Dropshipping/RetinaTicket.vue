<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketThread from "@/Components/Tickets/TicketThread.vue"
import Icon from "@/Components/Icon.vue"

defineProps<{
    pageHead: any
    title: string
    ticket: any
    comments: any[]
    routes: { comment: { name: string; parameters: Record<string, unknown> } }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 max-w-3xl space-y-4">
        <div class="flex items-center gap-3">
            <h2 class="text-lg font-semibold">{{ ticket.subject }}</h2>
            <span class="text-sm text-gray-600"><Icon :data="ticket.status_icon" /> {{ ticket.status_label }}</span>
        </div>
        <TicketThread :ticket="ticket" :comments="comments" :comment-route="routes.comment" />
    </div>
</template>
