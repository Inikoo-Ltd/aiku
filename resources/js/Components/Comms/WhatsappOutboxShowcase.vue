<script setup lang="ts">
import { ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import Select from "primevue/select"
import InputNumber from "primevue/inputnumber"
import ToggleSwitch from "primevue/toggleswitch"
import Tag from "primevue/tag"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { faEnvelope, faExclamationTriangle } from "@fal"
import { routeType } from "@/types/route"

interface TemplateOption {
    id: number
    name: string
    language: string | null
    status: string | null
    category: string | null
    is_usable: boolean
}

const props = defineProps<{
    data?: {
        id: number
        name: string
        code: string
        state: string
        is_active: boolean
        is_sending: boolean
        is_connected: boolean
        can_edit: boolean
        last_sent_at: string | null
        is_bulk: boolean
        interval: number | null
        needs_interval: boolean
        template: (TemplateOption & { body: string | null; tags: string[] }) | null
        templates: TemplateOption[]
        stats: { sent: number; delivered: number; read: number; failed: number; sent_1w: number; failed_1w: number }
        email_outbox: { name: string; state: string; route: routeType } | null
        update_route: routeType
    }
    tab?: string
}>()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"
const toggleClass = "[&.p-toggleswitch-checked_.p-toggleswitch-slider]:!bg-[var(--app-accent)]"

const isSaving = ref(false)
const errors = ref<Record<string, string>>({})

const intervalValue = ref<number | null>(props.data?.interval ?? null)

watch(
    () => props.data?.interval,
    (interval) => {
        intervalValue.value = interval ?? null
    }
)

const saveInterval = () => {
    if (intervalValue.value === (props.data?.interval ?? null)) {
        return
    }

    save({ interval: intervalValue.value })
}

const placeholder = (index: number) => "{" + "{" + (index + 1) + "}" + "}"

const templateLabel = (template: TemplateOption) => [template.name, template.language, template.category].filter(Boolean).join(" · ")

const save = (changes: Record<string, unknown>) => {
    if (!props.data) {
        return
    }

    errors.value = {}

    router.patch(route(props.data.update_route.name, props.data.update_route.parameters), changes, {
        preserveScroll: true,
        preserveState: true,
        only: ["showcase", "pageHead"],
        onStart: () => {
            isSaving.value = true
        },
        onFinish: () => {
            isSaving.value = false
        },
        onError: (formErrors) => {
            errors.value = formErrors
        },
    })
}
</script>

<template>
    <div class="px-4 py-5 sm:px-6">
        <div v-if="!data" class="space-y-3">
            <div class="skeleton h-24 rounded" />
            <div class="skeleton h-48 rounded" />
        </div>

        <div v-else class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <div v-if="!data.is_connected" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
                    <span>{{ ctrans("This shop has no WhatsApp number connected, so nothing will be sent until one is connected.") }}</span>
                </div>

                <section class="rounded-lg border border-gray-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon :icon="faWhatsapp" :class="data.is_sending ? 'text-[#25D366]' : 'text-gray-300'" class="text-xl" fixed-width aria-hidden="true" />
                            <div>
                                <div class="text-sm font-semibold text-gray-800">{{ data.name }}</div>
                                <div class="text-xs text-gray-500">
                                    <Tag v-if="data.is_sending" severity="success" :value="ctrans('Sending')" />
                                    <Tag v-else-if="data.is_active" severity="warn" :value="ctrans('Active, but not sending')" />
                                    <Tag v-else severity="secondary" :value="ctrans('Suspended')" />
                                </div>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <LoadingIcon v-if="isSaving" />
                            <ToggleSwitch
                                :modelValue="data.is_active"
                                :disabled="!data.can_edit || isSaving"
                                :class="toggleClass"
                                @update:modelValue="(value: boolean) => save({ state: value })"
                            />
                            <span>{{ ctrans("Active") }}</span>
                        </label>
                    </div>
                    <p v-if="errors.state" class="mt-2 text-xs text-red-600">{{ errors.state }}</p>
                </section>

                <section v-if="data.needs_interval" class="rounded-lg border border-gray-200 p-4">
                    <label for="whatsapp-outbox-interval" class="mb-1 block text-xs font-medium text-gray-700">
                        {{ ctrans("Hours between runs") }} <span class="text-red-500">*</span>
                    </label>
                    <InputNumber
                        inputId="whatsapp-outbox-interval"
                        v-model="intervalValue"
                        :min="1"
                        :max="720"
                        :useGrouping="false"
                        suffix=" h"
                        :disabled="!data.can_edit || isSaving"
                        :class="fieldFocusClass"
                        @blur="saveInterval"
                    />
                    <p v-if="errors.interval" class="mt-1 text-xs text-red-600">{{ errors.interval }}</p>
                    <p class="mt-2 text-xs text-gray-500">
                        {{ ctrans("Each run tells customers about the products in their order that ran out since the run before.") }}
                    </p>
                </section>

                <section class="rounded-lg border border-gray-200 p-4">
                    <label for="whatsapp-outbox-template" class="mb-1 block text-xs font-medium text-gray-700">
                        {{ ctrans("WhatsApp template") }}
                    </label>
                    <Select
                        inputId="whatsapp-outbox-template"
                        :modelValue="data.template?.id ?? null"
                        :options="data.templates"
                        :optionLabel="templateLabel"
                        optionValue="id"
                        :optionDisabled="(template: TemplateOption) => !template.is_usable"
                        :disabled="!data.can_edit || isSaving"
                        :placeholder="ctrans('Choose an approved UTILITY template')"
                        :class="fieldFocusClass"
                        showClear
                        filter
                        fluid
                        @update:modelValue="(value: number | null) => save({ meta_message_template_id: value ?? null })"
                    />
                    <p v-if="errors.meta_message_template_id" class="mt-1 text-xs text-red-600">{{ errors.meta_message_template_id }}</p>
                    <p class="mt-2 text-xs text-gray-500">
                        {{ ctrans("Keep the wording factual: promotional wording makes Meta reclassify a template as MARKETING, and marketing templates cannot be used here.") }}
                    </p>

                    <div v-if="data.template" class="mt-4">
                        <div class="mb-1 text-xs font-medium text-gray-500">{{ ctrans("Preview") }}</div>
                        <div class="max-w-md whitespace-pre-line rounded-lg bg-[#dcf8c6] px-3 py-2 text-sm text-gray-800 shadow-sm">{{ data.template.body ?? data.template.name }}</div>
                        <div v-if="data.template.tags.length" class="mt-2 flex flex-wrap gap-1 text-xs text-gray-500">
                            <span v-for="(tag, index) in data.template.tags" :key="index" class="whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5">
                                {{ placeholder(index) }} = {{ tag }}
                            </span>
                        </div>
                    </div>
                </section>
            </div>

            <div class="space-y-5">
                <section class="grid grid-cols-2 gap-3">
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Sent") }}</div>
                        <div class="text-xl font-semibold tabular-nums">{{ data.stats.sent }}</div>
                        <div class="text-xs text-gray-400">{{ ctrans(":count last week", { count: data.stats.sent_1w }) }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Failed") }}</div>
                        <div class="text-xl font-semibold tabular-nums" :class="data.stats.failed ? 'text-red-600' : ''">{{ data.stats.failed }}</div>
                        <div class="text-xs text-gray-400">{{ ctrans(":count last week", { count: data.stats.failed_1w }) }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Delivered") }}</div>
                        <div class="text-xl font-semibold tabular-nums">{{ data.stats.delivered }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Read") }}</div>
                        <div class="text-xl font-semibold tabular-nums">{{ data.stats.read }}</div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 p-3 text-xs text-gray-600">
                    <div class="flex justify-between gap-2">
                        <span>{{ ctrans("Last sent") }}</span>
                        <span class="text-gray-800">{{ data.last_sent_at ? useFormatTime(data.last_sent_at, { formatTime: "aiku" }) : "-" }}</span>
                    </div>
                    <div v-if="data.email_outbox" class="mt-2 flex items-center justify-between gap-2">
                        <span>{{ ctrans("Email outbox") }}</span>
                        <Link :href="route(data.email_outbox.route.name, data.email_outbox.route.parameters)" class="primaryLink inline-flex items-center gap-1">
                            <FontAwesomeIcon :icon="faEnvelope" fixed-width aria-hidden="true" />
                            {{ data.email_outbox.name }}
                        </Link>
                    </div>
                    <p class="mt-2 text-gray-400">{{ ctrans("The email and WhatsApp outboxes are switched on and off separately.") }}</p>
                </section>
            </div>
        </div>
    </div>
</template>
