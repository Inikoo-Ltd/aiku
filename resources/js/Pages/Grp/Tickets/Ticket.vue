<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketThread from "@/Components/Tickets/TicketThread.vue"
import TicketRating from "@/Components/Tickets/TicketRating.vue"
import { Select, MultiSelect } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useStaffMessaging } from "@/Stores/staff-messaging"

const props = defineProps<{
    pageHead: any
    title: string
    ticket: any
    comments: any[]
    can_rate: boolean
    options: {
        statuses: { label: string; value: string }[]
        priorities: { label: string; value: string }[]
        assignees: { label: string; value: number }[]
        tags: string[]
        kinds: { label: string; value: string }[]
        modules: { label: string; value: string }[]
    }
    routes: {
        update: { name: string; parameters: Record<string, unknown> }
        comment: { name: string; parameters: Record<string, unknown> }
        rate: { name: string; parameters: Record<string, unknown> }
        escalate: { name: string; parameters: Record<string, unknown> }
    }
}>()

const staffMessaging = useStaffMessaging()

const newTag = ref("")
const tagOptions = computed(() => Array.from(new Set([...props.options.tags, ...props.ticket.tags])))
const addTypedTag = () => {
    const tag = newTag.value.trim().toLowerCase()
    if (!tag || props.ticket.tags.includes(tag)) return
    update("tags", [...props.ticket.tags, tag])
    newTag.value = ""
}

const escalate = () => router.post(route(props.routes.escalate.name, props.routes.escalate.parameters))

const openStaffChat = async () => {
    if (!staffMessaging.fetched) await staffMessaging.fetchConversations()
    staffMessaging.openConversation(props.ticket.staff_conversation_ulid)
}

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
            <TicketRating :rating="ticket.rating" :rating-comment="ticket.rating_comment" :can-rate="can_rate" :rate-route="routes.rate" />
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
            <div v-if="ticket.type === 'help'" class="grid grid-cols-2 gap-2">
                <div>
                    <p class="text-xs text-gray-500 mb-1">{{ trans("Kind") }}</p>
                    <Select :model-value="ticket.kind" :options="options.kinds" option-label="label" option-value="value" show-clear class="w-full" @update:model-value="update('kind', $event)" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">{{ trans("Module") }}</p>
                    <Select :model-value="ticket.module" :options="options.modules" option-label="label" option-value="value" show-clear filter class="w-full" @update:model-value="update('module', $event)" />
                </div>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">{{ trans("Tags") }}</p>
                <MultiSelect :model-value="ticket.tags" :options="tagOptions" display="chip" filter class="w-full" :placeholder="trans('e.g. not a bug')" @update:model-value="update('tags', $event)" @filter="newTag = $event.value" @keydown.enter="addTypedTag">
                    <template #emptyfilter><button type="button" class="text-sm text-blue-600 px-2 py-1" @click="addTypedTag">{{ trans("Add") }} "{{ newTag }}"</button></template>
                </MultiSelect>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">{{ trans("Assignee") }}</p>
                <Select :model-value="ticket.assignee_id" :options="options.assignees" option-label="label" option-value="value" show-clear filter class="w-full" :placeholder="trans('Unassigned')" @update:model-value="update('assignee_id', $event)" />
            </div>
            <Button v-if="ticket.type === 'customer' && !ticket.escalations.length" type="secondary" icon="fal fa-level-up" :label="trans('Escalate to help desk')" full @click="escalate" />
            <Button v-if="ticket.staff_conversation_ulid" type="tertiary" icon="fal fa-comments" :label="trans('Staff chat')" full @click="openStaffChat" />
            <label class="flex items-center gap-x-2 text-gray-600 cursor-pointer">
                <input type="checkbox" :checked="ticket.is_confidential" class="rounded border-gray-300" @change="update('is_confidential', ($event.target as HTMLInputElement).checked)" />
                {{ trans("Confidential") }} <span class="text-xs text-gray-400">({{ trans("only reporter, assignee and admins") }})</span>
            </label>
            <dl class="space-y-1 text-gray-600">
                <div class="flex justify-between"><dt>{{ trans("Type") }}</dt><dd>{{ ticket.type }}</dd></div>
                <div v-if="ticket.parent" class="flex justify-between"><dt>{{ trans("Escalated from") }}</dt><dd><Link :href="route('grp.tickets.show', ticket.parent)" class="text-blue-600 hover:underline">{{ ticket.parent }}</Link></dd></div>
                <div v-if="ticket.escalations.length" class="flex justify-between"><dt>{{ trans("Escalated to") }}</dt><dd class="space-x-1"><Link v-for="ref in ticket.escalations" :key="ref" :href="route('grp.tickets.show', ref)" class="text-blue-600 hover:underline">{{ ref }}</Link></dd></div>
                <div class="flex justify-between"><dt>{{ trans("Reporter") }}</dt><dd>{{ ticket.reporter || "-" }}</dd></div>
                <div v-if="ticket.customer" class="flex justify-between"><dt>{{ trans("Customer") }}</dt><dd>{{ ticket.customer }}</dd></div>
                <div v-if="ticket.shop" class="flex justify-between"><dt>{{ trans("Shop") }}</dt><dd>{{ ticket.shop }}</dd></div>
            </dl>
        </aside>
    </div>
</template>
