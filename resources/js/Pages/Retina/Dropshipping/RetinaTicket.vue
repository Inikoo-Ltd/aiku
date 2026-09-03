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
import TicketRating from "@/Components/Tickets/TicketRating.vue"
import Icon from "@/Components/Icon.vue"

defineProps<{
    pageHead: any
    title: string
    ticket: any
    comments: any[]
    can_rate: boolean
    routes: { comment: { name: string; parameters: Record<string, unknown> }; rate: { name: string; parameters: Record<string, unknown> } }
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
        <TicketRating :rating="ticket.rating" :rating-comment="ticket.rating_comment" :can-rate="can_rate" :rate-route="routes.rate" />
        <TicketThread :ticket="ticket" :comments="comments" :comment-route="routes.comment" />
    </div>
</template>
