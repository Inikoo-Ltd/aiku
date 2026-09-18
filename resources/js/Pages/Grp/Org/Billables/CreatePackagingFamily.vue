<!--
  -  Author: Andi Ferdiawan
  -  Created: Thu, 09 Jul 2026 16:00:00 Central Indonesia Time, Bali, Indonesia
  -  Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { ref } from "vue"
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
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage, faPlus } from "@fal"

library.add(faImage, faPlus)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    typeOptions: { label: string, value: string }[]
    currencyCode: string
    storeRoute: routeType
}>()

interface PackagingVariant {
    code: string
    name: string
    price: number
    width: number | null
    height: number | null
    depth: number | null
    image: File | null
}

const newVariant = (): PackagingVariant => ({
    code: "",
    name: "",
    price: 0,
    width: null,
    height: null,
    depth: null,
    image: null,
})

const form = useForm<{
    family_code: string
    type: string | null
    packagings: PackagingVariant[]
}>({
    family_code: "",
    type: null,
    packagings: [newVariant()],
})

const imagePreviews = ref<(string | null)[]>([null])

const addVariant = () => {
    form.packagings.push(newVariant())
    imagePreviews.value.push(null)
}

const removeVariant = (index: number) => {
    if (form.packagings.length === 1) {
        return
    }
    form.packagings.splice(index, 1)
    imagePreviews.value.splice(index, 1)
}

const onImageSelected = (index: number, event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null
    form.packagings[index].image = file
    imagePreviews.value[index] = file ? URL.createObjectURL(file) : null
}

const clearImage = (index: number) => {
    form.packagings[index].image = null
    imagePreviews.value[index] = null
}

const submit = () => {
    form.post(route(props.storeRoute.name, props.storeRoute.parameters))
}

const variantError = (index: number, field: string) => (form.errors as Record<string, string>)[`packagings.${index}.${field}`]
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <form class="p-4 sm:p-6 mx-auto w-full max-w-5xl space-y-6" @submit.prevent="submit">
        <!-- Shared family fields -->
        <section class="rounded-lg border border-gray-200 bg-white p-4 sm:p-6">
            <h2 class="text-base font-semibold mb-4">{{ trans("Family") }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">
                        {{ trans("Family code") }} <span class="text-red-500">*</span>
                    </label>
                    <PureInput v-model="form.family_code" :placeholder="trans('e.g. GIFT-BOX')" required />
                    <p class="mt-1 text-xs text-gray-500">
                        {{ trans("Groups size variants of the same packaging, e.g. GIFT-BOX for its small, medium and large sizes") }}
                    </p>
                    <p v-if="form.errors.family_code" class="mt-1 text-xs text-red-500">{{ form.errors.family_code }}</p>
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
            </div>
        </section>

        <!-- Variants -->
        <section class="rounded-lg border border-gray-200 bg-white p-4 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold">{{ trans("Packagings in this family") }}</h2>
                <Button type="tertiary" icon="fal fa-plus" :label="trans('Add packaging')" @click="addVariant" />
            </div>

            <div class="space-y-4">
                <div
                    v-for="(variant, index) in form.packagings"
                    :key="index"
                    class="relative rounded-lg border border-gray-200 p-4"
                >
                    <div v-if="form.packagings.length > 1" class="absolute right-3 top-3">
                        <Button type="delete" size="xs" :tooltip="trans('Remove')" @click="removeVariant(index)" />
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <!-- Image picker -->
                        <div class="shrink-0">
                            <label class="block text-sm font-medium mb-1">{{ trans("Image") }}</label>
                            <label
                                class="flex h-24 w-24 cursor-pointer items-center justify-center rounded-lg border border-dashed border-gray-300 hover:border-gray-400 overflow-hidden"
                            >
                                <img v-if="imagePreviews[index]" :src="imagePreviews[index]!" class="h-full w-full object-cover" alt="" />
                                <FontAwesomeIcon v-else :icon="['fal', 'image']" class="text-2xl text-gray-400" aria-hidden="true" />
                                <input type="file" accept="image/*" class="hidden" @change="onImageSelected(index, $event)" />
                            </label>
                            <Button
                                v-if="imagePreviews[index]"
                                type="negative"
                                size="xxs"
                                class="mt-1"
                                :label="trans('Remove image')"
                                @click="clearImage(index)"
                            />
                            <p v-if="variantError(index, 'image')" class="mt-1 text-xs text-red-500">{{ variantError(index, 'image') }}</p>
                        </div>

                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">
                                    {{ trans("Code") }} <span class="text-red-500">*</span>
                                </label>
                                <PureInput v-model="variant.code" :placeholder="trans('e.g. GIFT-BOX-S')" required />
                                <p v-if="variantError(index, 'code')" class="mt-1 text-xs text-red-500">{{ variantError(index, 'code') }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium mb-1">
                                    {{ trans("Name") }} <span class="text-red-500">*</span>
                                </label>
                                <PureInput v-model="variant.name" :placeholder="trans('e.g. Gift Box Small')" required />
                                <p v-if="variantError(index, 'name')" class="mt-1 text-xs text-red-500">{{ variantError(index, 'name') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">
                                    {{ trans("Price") }} <span class="text-red-500">*</span>
                                </label>
                                <InputNumber
                                    v-model="variant.price"
                                    mode="currency"
                                    :currency="currencyCode"
                                    :minFractionDigits="2"
                                    :maxFractionDigits="2"
                                    :min="0"
                                    fluid
                                />
                                <p v-if="variantError(index, 'price')" class="mt-1 text-xs text-red-500">{{ variantError(index, 'price') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">{{ trans("Width (mm)") }}</label>
                                <InputNumber v-model="variant.width" :min="0" fluid />
                                <p v-if="variantError(index, 'width')" class="mt-1 text-xs text-red-500">{{ variantError(index, 'width') }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">{{ trans("Height (mm)") }}</label>
                                    <InputNumber v-model="variant.height" :min="0" fluid />
                                    <p v-if="variantError(index, 'height')" class="mt-1 text-xs text-red-500">{{ variantError(index, 'height') }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">{{ trans("Depth (mm)") }}</label>
                                    <InputNumber v-model="variant.depth" :min="0" fluid />
                                    <p v-if="variantError(index, 'depth')" class="mt-1 text-xs text-red-500">{{ variantError(index, 'depth') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="form.errors.packagings" class="mt-2 text-xs text-red-500">{{ form.errors.packagings }}</p>
        </section>

        <div class="flex justify-end">
            <Button type="save" :loading="form.processing" :label="trans('Save')" @click="submit" />
        </div>
    </form>
</template>
