<!--
  -  Author: Andi Ferdiawan
  -  Created: Thu, 09 Jul 2026 17:00:00 Central Indonesia Time, Bali, Indonesia
  -  Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Head, useForm } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import type { Image as ImageProxy } from "@/types/Image"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import Image from "@common/Components/Image.vue"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage } from "@fal"

library.add(faImage)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    packaging: {
        id: number
        slug: string
        family_code: string
        type: string
        code: string
        name: string
        price: number
        width: number | null
        height: number | null
        depth: number | null
        image: { thumbnail?: ImageProxy, source?: ImageProxy } | null
    }
    typeOptions: { label: string, value: string }[]
    currencyCode: string
    updateRoute: routeType
}>()

const form = useForm<{
    family_code: string
    type: string
    code: string
    name: string
    price: number
    width: number | null
    height: number | null
    depth: number | null
    image: File | null
}>({
    family_code: props.packaging.family_code,
    type: props.packaging.type,
    code: props.packaging.code,
    name: props.packaging.name,
    price: props.packaging.price,
    width: props.packaging.width,
    height: props.packaging.height,
    depth: props.packaging.depth,
    image: null,
})

const newImagePreview = ref<string | null>(null)

const onImageSelected = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null
    form.image = file
    newImagePreview.value = file ? URL.createObjectURL(file) : null
}

const clearImage = () => {
    form.image = null
    newImagePreview.value = null
}

const submit = () => {
    form
        .transform((data) => ({
            ...(data.image ? data : { ...data, image: undefined }),
            _method: "patch",
        }))
        .post(route(props.updateRoute.name, props.updateRoute.parameters))
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <form class="p-4 sm:p-6 mx-auto w-full max-w-5xl space-y-6" @submit.prevent="submit">
        <!-- Family fields -->
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

        <!-- Packaging -->
        <section class="rounded-lg border border-gray-200 bg-white p-4 sm:p-6">
            <h2 class="text-base font-semibold mb-4">{{ trans("Packaging") }}</h2>

            <div class="rounded-lg border border-gray-200 p-4">
                <div class="flex flex-col sm:flex-row gap-4">
                    <!-- Image picker -->
                    <div class="shrink-0">
                        <label class="block text-sm font-medium mb-1">{{ trans("Image") }}</label>
                        <label
                            class="flex h-24 w-24 cursor-pointer items-center justify-center rounded-lg border border-dashed border-gray-300 hover:border-gray-400 overflow-hidden"
                        >
                            <img v-if="newImagePreview" :src="newImagePreview" class="h-full w-full object-cover" alt="" />
                            <Image
                                v-else-if="packaging.image"
                                :src="packaging.image.source"
                                :alt="packaging.name"
                                class="h-full w-full object-cover"
                            />
                            <FontAwesomeIcon v-else :icon="['fal', 'image']" class="text-2xl text-gray-400" aria-hidden="true" />
                            <input type="file" accept="image/*" class="hidden" @change="onImageSelected" />
                        </label>
                        <Button
                            v-if="newImagePreview"
                            type="negative"
                            size="xxs"
                            class="mt-1"
                            :label="trans('Undo change')"
                            @click="clearImage"
                        />
                        <p v-if="form.errors.image" class="mt-1 text-xs text-red-500">{{ form.errors.image }}</p>
                    </div>

                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">
                                {{ trans("Code") }} <span class="text-red-500">*</span>
                            </label>
                            <PureInput v-model="form.code" :placeholder="trans('e.g. GIFT-BOX-S')" required />
                            <p v-if="form.errors.code" class="mt-1 text-xs text-red-500">{{ form.errors.code }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium mb-1">
                                {{ trans("Name") }} <span class="text-red-500">*</span>
                            </label>
                            <PureInput v-model="form.name" :placeholder="trans('e.g. Gift Box Small')" required />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
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
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ trans("Width (mm)") }}</label>
                            <InputNumber v-model="form.width" :min="0" fluid />
                            <p v-if="form.errors.width" class="mt-1 text-xs text-red-500">{{ form.errors.width }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">{{ trans("Height (mm)") }}</label>
                                <InputNumber v-model="form.height" :min="0" fluid />
                                <p v-if="form.errors.height" class="mt-1 text-xs text-red-500">{{ form.errors.height }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">{{ trans("Depth (mm)") }}</label>
                                <InputNumber v-model="form.depth" :min="0" fluid />
                                <p v-if="form.errors.depth" class="mt-1 text-xs text-red-500">{{ form.errors.depth }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <Button type="save" :loading="form.processing" :label="trans('Save')" @click="submit" />
        </div>
    </form>
</template>
