<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { useForm } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"

const props = defineProps<{
    ticket: { description: string | null; reporter: string | null; created_at: string; images?: Record<string, string>[] }
    comments: { id: number; body: string; is_internal: boolean; is_staff: boolean; author: string | null; created_at: string; images?: Record<string, string>[] }[]
    commentRoute: { name: string; parameters: Record<string, unknown> }
    allowInternal?: boolean
}>()

const form = useForm<{ body: string; is_internal: boolean; images: File[] }>({ body: "", is_internal: false, images: [] })

const submit = () => {
    form.post(route(props.commentRoute.name, props.commentRoute.parameters), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <div class="space-y-4">
        <div class="bg-white rounded-lg border border-gray-300 p-4">
            <div class="text-xs text-gray-500 mb-2">
                <span class="font-medium text-gray-700">{{ ticket.reporter || trans("Unknown") }}</span>
                · {{ useFormatTime(ticket.created_at, { formatTime: "hm" }) }}
            </div>
            <TicketBody v-if="ticket.description || ticket.images?.length" :text="ticket.description" :images="ticket.images" />
            <p v-else class="text-sm text-gray-400">{{ trans("No description") }}</p>
        </div>

        <div
            v-for="comment in comments"
            :key="comment.id"
            class="rounded-lg border p-4"
            :class="comment.is_internal ? 'bg-amber-50 border-amber-200' : comment.is_staff ? 'bg-white border-gray-300' : 'bg-blue-50 border-blue-200'"
        >
            <div class="text-xs text-gray-500 mb-2 flex items-center gap-2">
                <span class="font-medium text-gray-700">{{ comment.author || trans("Unknown") }}</span>
                · {{ useFormatTime(comment.created_at, { formatTime: "hm" }) }}
                <span v-if="comment.is_internal" class="px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 text-[10px] font-medium">{{ trans("Internal") }}</span>
            </div>
            <TicketBody :text="comment.body" :images="comment.images" />
        </div>

        <form class="bg-white rounded-lg border border-gray-300 p-4 space-y-3" @submit.prevent="submit">
            <TicketComposer v-model:body="form.body" v-model:images="form.images" :rows="4" :placeholder="trans('Write a comment, paste a screenshot or drop images')" />
            <p v-if="form.errors.body || form.errors.images" class="text-xs text-red-600">{{ form.errors.body || form.errors.images }}</p>
            <div class="flex items-center justify-between">
                <label v-if="allowInternal" class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="form.is_internal" type="checkbox" class="rounded border-gray-300" />
                    {{ trans("Internal note (hidden from customer)") }}
                </label>
                <span v-else />
                <Button :label="trans('Comment')" :loading="form.processing" :disabled="!form.body.trim() && !form.images.length" @click="submit" />
            </div>
        </form>
    </div>
</template>
