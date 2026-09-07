<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 05 Sep 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue"
import { useForm, usePage } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBug, faCheckCircle, faPaperPlane } from "@fal"

library.add(faBug, faCheckCircle, faPaperPlane)
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"

const readOnly = computed(() => !!(usePage().props as any).tickets_read_only)
const isOpen = ref(false)
const sentReference = ref<string | null>(null)

const form = useForm<{ subject: string; description: string; kind: string; stay: boolean; images: File[]; reference_url: string }>({
    subject: "",
    description: "",
    kind: "bug",
    stay: true,
    images: [],
    reference_url: "",
})

const open = () => {
    form.reset()
    form.reference_url = window.location.href
    sentReference.value = null
    isOpen.value = true
}

const submit = () =>
    form.post(route("grp.models.ticket.store"), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (page) => {
            sentReference.value = (page.props.flash as any)?.notification?.title ?? trans("Sent")
            setTimeout(() => (isOpen.value = false), 1200)
        },
    })

const onKey = (event: KeyboardEvent) => {
    if (!readOnly.value && event.altKey && event.shiftKey && event.key.toLowerCase() === "b") open()
}
onMounted(() => window.addEventListener("keydown", onKey))
onUnmounted(() => window.removeEventListener("keydown", onKey))
</script>

<template>
    <button
        v-if="!readOnly"
        type="button"
        class="fixed bottom-12 md:bottom-3 left-3 z-40 h-9 px-3 rounded-full bg-red-600 text-white shadow-lg flex items-center gap-x-2 text-sm hover:bg-red-700"
        :title="trans('Report a bug') + ' (Alt+Shift+B)'"
        @click="open">
        <FontAwesomeIcon icon="fal fa-bug" fixed-width aria-hidden="true" />
        <span class="hidden md:inline">{{ trans("Bug") }}</span>
    </button>

    <Modal :is-open="isOpen" width="w-full max-w-lg" @on-close="isOpen = false">
        <div v-if="sentReference" class="py-8 text-center text-lg font-semibold text-green-700">
            <FontAwesomeIcon icon="fal fa-check-circle" class="mr-2" /> {{ sentReference }}
        </div>
        <div v-else class="space-y-3">
            <p class="text-base font-semibold text-gray-900">{{ trans("Report a bug") }}</p>
            <input
                v-model="form.subject"
                type="text"
                maxlength="255"
                autofocus
                class="w-full rounded-md border-gray-300 text-sm focus:border-gray-500 focus:ring-0"
                :placeholder="trans('What is broken?')"
                @keydown.enter.prevent="form.subject.trim() && submit()" />
            <TicketComposer v-model:body="form.description" v-model:images="form.images" :rows="4" :placeholder="trans('Optional: what did you expect, paste a screenshot')" />
            <p class="text-xs text-gray-400 truncate">{{ form.reference_url }}</p>
            <p v-if="form.errors.subject" class="text-xs text-red-600">{{ form.errors.subject }}</p>
            <div class="flex justify-end gap-x-2">
                <Button type="tertiary" :label="trans('Cancel')" @click="isOpen = false" />
                <Button :label="trans('Send')" icon="fal fa-paper-plane" :loading="form.processing" :disabled="!form.subject.trim()" @click="submit" />
            </div>
        </div>
    </Modal>
</template>
