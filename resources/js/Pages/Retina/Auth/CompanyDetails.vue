<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3"
import RetinaShowIris from "@/Layouts/RetinaShowIris.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import InputText from "primevue/inputtext"
import InputError from "@/Components/InputError.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle, faSave, faSignIn } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed } from "vue"

library.add(faCheckCircle, faSave, faSignIn)

defineOptions({ layout: RetinaShowIris })

const props = defineProps<{
    title: string
    company: string
    address: string | null
    held: { ico: string | null; dic: string | null; vat_number: string | null }
    can_check: boolean
    save_url: string
    login_url: string
    saved: boolean
    labels: Record<"intro" | "saved" | "company" | "address" | "no_vat_payer" | "cannot_check" | "save" | "change_held" | "log_in", string>
}>()

const form = useForm({ ico: "", dic: "", vat_number: "" })

const fields = computed(() => [
    { key: "ico" as const, label: "IČO", held: props.held.ico, placeholder: "12345678", required: true },
    { key: "dic" as const, label: "DIČ", held: props.held.dic, placeholder: "2012345678", required: false },
    { key: "vat_number" as const, label: "IČ DPH", held: props.held.vat_number, placeholder: "SK2012345678", required: false },
])

const hasMissing = computed(() => fields.value.some((field) => !field.held))

const submit = () => form.post(props.save_url, { preserveScroll: true, onSuccess: () => form.reset() })
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto max-w-lg px-4 py-10">
        <h1 class="text-xl font-semibold text-gray-900">{{ title }}</h1>
        <p class="mt-2 text-sm text-gray-600">{{ labels.intro }}</p>

        <div v-if="saved" class="mt-6 flex items-center gap-2 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
            <FontAwesomeIcon icon="fal fa-check-circle" fixed-width aria-hidden="true" />
            {{ labels.saved }}
        </div>

        <dl class="mt-6 divide-y divide-gray-100 rounded border border-gray-200 text-sm">
            <div class="flex gap-4 px-3 py-2">
                <dt class="w-24 shrink-0 text-gray-500">{{ labels.company }}</dt>
                <dd class="text-gray-900">{{ company }}</dd>
            </div>
            <div class="flex gap-4 px-3 py-2">
                <dt class="w-24 shrink-0 text-gray-500">{{ labels.address }}</dt>
                <dd class="whitespace-pre-line text-gray-900">{{ address }}</dd>
            </div>
        </dl>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div v-for="field in fields" :key="field.key">
                <label :for="field.key" class="block text-sm font-medium text-gray-700">
                    {{ field.label }} <span v-if="field.required && !field.held" class="text-red-500">*</span>
                </label>
                <div v-if="field.held" class="mt-1 rounded bg-gray-50 px-3 py-2 font-mono text-sm whitespace-nowrap text-gray-900">{{ field.held }}</div>
                <template v-else>
                    <InputText
                        :id="field.key"
                        v-model="form[field.key]"
                        :placeholder="field.placeholder"
                        :disabled="!can_check"
                        class="mt-1 w-full"
                        @update:modelValue="() => (form.errors[field.key] = '')"
                    />
                    <p v-if="field.key === 'vat_number'" class="mt-1 text-xs text-gray-500">{{ labels.no_vat_payer }}</p>
                </template>
                <InputError class="mt-1" :message="form.errors[field.key]" />
            </div>

            <p v-if="!can_check" class="text-sm text-amber-700">{{ labels.cannot_check }}</p>

            <Button
                v-if="hasMissing"
                type="primary"
                full
                icon="fal fa-save"
                :label="labels.save"
                :loading="form.processing"
                :disabled="!can_check"
                @click="submit"
            />
        </form>

        <div class="mt-8 border-t border-gray-200 pt-4 text-sm text-gray-600">
            <p>{{ labels.change_held }}</p>
            <a :href="login_url" class="mt-2 inline-flex items-center gap-1 text-gray-700 underline hover:text-gray-900">
                <FontAwesomeIcon icon="fal fa-sign-in" fixed-width aria-hidden="true" />
                {{ labels.log_in }}
            </a>
        </div>
    </div>
</template>
