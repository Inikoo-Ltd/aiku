<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { useForm } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { Select } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"

const props = defineProps<{
    storeRoute: { name: string; parameters?: Record<string, unknown> }
    priorities: { label: string; value: string }[]
}>()

const form = useForm<{ subject: string; description: string; priority: string; images: File[] }>({
    subject: "",
    description: "",
    priority: "normal",
    images: [],
})

const submit = () => form.post(route(props.storeRoute.name, props.storeRoute.parameters ?? {}), { forceFormData: true })
</script>

<template>
    <form class="max-w-3xl space-y-4" @submit.prevent="submit">
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ trans("Subject") }}</label>
            <input v-model="form.subject" type="text" maxlength="255" class="w-full rounded-md border-gray-300 text-sm focus:border-gray-500 focus:ring-0" :placeholder="trans('One line that says what is wrong')" />
            <p v-if="form.errors.subject" class="text-xs text-red-600 mt-1">{{ form.errors.subject }}</p>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ trans("Details") }}</label>
            <TicketComposer v-model:body="form.description" v-model:images="form.images" :rows="8" />
            <p v-if="form.errors.description || form.errors.images" class="text-xs text-red-600 mt-1">{{ form.errors.description || form.errors.images }}</p>
        </div>
        <div class="max-w-xs">
            <label class="block text-xs text-gray-500 mb-1">{{ trans("Priority") }}</label>
            <Select v-model="form.priority" :options="priorities" option-label="label" option-value="value" class="w-full" />
        </div>
        <div class="flex justify-end">
            <Button :label="trans('Create ticket')" :loading="form.processing" :disabled="!form.subject.trim()" @click="submit" />
        </div>
    </form>
</template>
