<!--
  -  Author: Andi Ferdiawan
  -  Created: Fri, 10 Jul 2026 14:00:00 Central Indonesia Time, Bali, Indonesia
  -  Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import Checkbox from "primevue/checkbox"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    typeOptions: { label: string, value: string }[]
    familyCodeOptions: { label: string, value: string, packagings: string[] }[]
    currencyCode: string
    storeRoute: routeType
}>()

const escapeHtml = (text: string) =>
    text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")

const familyTooltip = (option: { packagings: string[] }) => ({
    content: `<ul class="list-disc pl-4 space-y-0.5">${option.packagings
        .map((packaging) => `<li>${escapeHtml(packaging)}</li>`)
        .join("")}</ul>`,
    html: true,
})

const form = useForm<{
    name: string
    type: string | null
    price: number
    family_codes: string[]
}>({
    name: "",
    type: null,
    price: 0,
    family_codes: [],
})

const submit = () => {
    form.post(route(props.storeRoute.name, props.storeRoute.parameters))
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <form class="p-4 sm:p-6 mx-auto w-full max-w-3xl space-y-6" @submit.prevent="submit">
        <section class="rounded-lg border border-gray-200 bg-white p-4 sm:p-6">
            <h2 class="text-base font-semibold mb-4">{{ trans("Leaflet") }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">
                        {{ trans("Name") }} <span class="text-red-500">*</span>
                    </label>
                    <PureInput v-model="form.name" :placeholder="trans('e.g. Thank You Card')" required />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">
                        {{ trans("Type") }} <span class="text-red-500">*</span>
                    </label>
                    <Select
                        v-model="form.type"
                        :options="typeOptions"
                        optionLabel="label"
                        optionValue="value"
                        :placeholder="trans('Select a type')"
                        class="w-full"
                    />
                    <p v-if="form.errors.type" class="mt-1 text-xs text-red-500">{{ form.errors.type }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">
                        {{ trans("Price") }} <span class="text-red-500">*</span>
                    </label>
                    <InputNumber
                        v-model="form.price"
                        mode="currency"
                        :currency="currencyCode"
                        :minFractionDigits="2"
                        :maxFractionDigits="2"
                        :min="0"
                        fluid
                    />
                    <p v-if="form.errors.price" class="mt-1 text-xs text-red-500">{{ form.errors.price }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium mb-1">{{ trans("Packaging families") }}</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 rounded-lg border border-gray-200 p-3">
                        <label
                            v-for="option in familyCodeOptions"
                            :key="option.value"
                            class="flex items-center gap-2 text-sm cursor-pointer"
                            v-tooltip="familyTooltip(option)"
                        >
                            <Checkbox v-model="form.family_codes" :value="option.value" />
                            <span>{{ option.label }}</span>
                        </label>
                        <p v-if="!familyCodeOptions.length" class="col-span-full text-sm text-gray-400">
                            {{ trans("No packaging families available") }}
                        </p>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ trans("The leaflet applies to every packaging within the selected families") }}
                    </p>
                    <p v-if="form.errors.family_codes" class="mt-1 text-xs text-red-500">{{ form.errors.family_codes }}</p>
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <Button type="save" :loading="form.processing" :label="trans('Save')" @click="submit" />
        </div>
    </form>
</template>
