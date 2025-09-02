<script setup lang="ts">
import { ref } from 'vue'
import Modal from '@/Components/Utils/Modal.vue'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { notify } from '@kyvg/vue3-notification'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { set } from 'lodash-es'
import PureImageCrop from '@/Components/Pure/PureImageCrop.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import { routeType } from '@/types/route'
import { faPlus } from "@fas"
import { faTrashAlt } from "@fal"
library.add(faPlus, faTrashAlt)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        brand_routes: {
            index_brand: routeType
            store_brand: routeType
            update_brand: routeType
            delete_brand: routeType
        }
    }
}>()

// Section: modal create new brand
const isModalBrand = ref(false)
const newBrandImg = ref<File | null>(null)
const newBrandName = ref('')
const newBrandReference = ref('')
const isLoadingCreateBrand = ref(false)
const onCreateNewBrand = () => {
    router.post(
        route(props.fieldData?.brand_routes.store_brand.name, props.fieldData?.brand_routes.store_brand.parameters),
        {
            name: newBrandName.value,
            reference: newBrandReference.value,
            image: newBrandImg.value,
        },
        {
            onStart: () => {
                isLoadingCreateBrand.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully created new brand") + ': ' + newBrandName.value,
                    type: "success",
                })
                isModalBrand.value = false
                newBrandName.value = ''
                newBrandReference.value = ''
            },
            onFinish: () => {
                isLoadingCreateBrand.value = false
            },
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: error.message,
                    type: "error",
                })
            }
        }
    )
}

// Section: edit brand
const isModalUpdateBrand = ref(false)
const isLoadingUpdateBrand = ref(false)
const selectedBrandToUpdate = ref<any>(null)
const onEditBrand = () => {
    router[props.fieldData?.brand_routes.update_brand.method || 'patch'](
        route(props.fieldData?.brand_routes.update_brand.name, {
            ...props.fieldData?.brand_routes.update_brand.parameters,
            brand: selectedBrandToUpdate.value?.id,
        }),
        {
            reference: selectedBrandToUpdate.value?.reference,
            name: selectedBrandToUpdate.value?.name,
        },
        {
            onStart: () => {
                isLoadingUpdateBrand.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success!"),
                    text: trans("Successfully update brand name."),
                    type: "success",
                })
                isModalUpdateBrand.value = false
                selectedBrandToUpdate.value = null
            },
            onFinish: () => {
                isLoadingUpdateBrand.value = false
            },
            onError: (error) => {
                console.error('Error editing brand:', error)
                notify({
                    title: trans("Something went wrong"),
                    text: error.message || trans('Failed to update brand'),
                    type: "error",
                })
            }
        }
    )
}
</script>

<template>
    <div v-if="props.fieldData?.brand_routes?.index_brand" class="w-full max-w-md py-4 gap-x-3 ">
        <div class="w-full">
            <PureMultiselectInfiniteScroll
                v-model="form[fieldName]"
                @update:modelValue="(e) => set(form, [fieldName], e)"
                :fetchRoute="props.fieldData.brand_routes.index_brand"
                :placeholder="trans('Select brand')"
                valueProp="id"
                :initOptions="form[fieldName] ? form[fieldName] : undefined"
            >
                <template #singlelabel="{ value }">
                    <div class="w-full text-left pl-4">{{ value.name }}</div>
                </template>

                <template #option="{ option }">
                    <div class="flex justify-between w-full">
                        {{ option.name }}
                        <div class="flex gap-x-2">
                            <ModalConfirmationDelete
                                :routeDelete="{
                                    name: props.fieldData?.brand_routes.delete_brand.name,
                                    parameters: {
                                        ...props.fieldData?.brand_routes.delete_brand.parameters,
                                        brand: option.id,
                                    }
                                }"
                                :title="trans('Are you sure you want to delete brand') + ` ${option.name}?`"
                                isFullLoading
                            >
                                <template #default="{ changeModel }">
                                    <div @click.stop="changeModel"
                                        class="cursor-pointer px-1 text-red-400 hover:text-red-600 rounded-sm">
                                        <FontAwesomeIcon icon="fal fa-trash-alt" aria-hidden="true" />
                                    </div>
                                </template>
                            </ModalConfirmationDelete>

                            <div @click.stop="(selectedBrandToUpdate = option, isModalUpdateBrand = true)"
                                class="text-gray-400 hover:text-gray-500">
                                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                            </div>
                        </div>
                    </div>
                </template>

                <template #afterlist>
                    <div class="w-full p-2 border-t border-gray-300">
                        <Button @click="() => (isModalBrand = true)" label="Add new brand" icon="fas fa-plus" full
                            type="secondary" />
                    </div>
                </template>
            </PureMultiselectInfiniteScroll>
        </div>
    </div>

    <!-- Modal: create new brand -->
    <Modal :isOpen="isModalBrand" @onClose="isModalBrand = false" width="w-[600px]">
        <div class="isolate bg-white px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans('Create new brand') }}</h2>
            </div>

            <div class="mt-7 grid grid-cols-1 gap-y-4 sm:grid-cols-2">
                <div class="col-span-2">
                    <label class="block text-sm font-medium leading-6">
                        {{ trans('Image') }}
                    </label>
                    <div class="mt-1">
                        <PureImageCrop :aspectRatio="1 / 1" @cropped="(e) => newBrandImg = e" />
                    </div>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium leading-6">
                        <span class="text-red-500">*</span> {{ trans('Reference') }}
                    </label>
                    <div class="mt-1">
                        <PureInput v-model="newBrandReference" placeholder="1-16 characters" />
                    </div>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium leading-6">
                        <span class="text-red-500">*</span> {{ trans('Name') }}
                    </label>
                    <div class="mt-1">
                        <PureInput v-model="newBrandName" placeholder="1-64 characters" />
                    </div>
                </div>
            </div>

            <div class="mt-6 mb-4 relative">
                <Button @click="() => onCreateNewBrand()" label="Create" :disabled="!newBrandName"
                    :loading="isLoadingCreateBrand" full />
            </div>
        </div>
    </Modal>

    <!-- Modal: Edit brand -->
    <Modal :isOpen="isModalUpdateBrand" @onClose="isModalUpdateBrand = false" width="w-[600px]">
        <div class="isolate bg-white px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans('Edit brand') }}</h2>
            </div>

            <div class="mt-7 grid grid-cols-1 gap-y-4 sm:grid-cols-2">
                <div class="col-span-2">
                    <label class="block text-sm font-medium leading-6">
                        {{ trans('Image') }}
                    </label>
                    <div class="mt-1">
                        <PureImageCrop :src_image="selectedBrandToUpdate?.image" :aspectRatio="1 / 1"
                            @cropped="(e) => selectedBrandToUpdate.image = e" />
                    </div>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium leading-6">
                        <span class="text-red-500">*</span> {{ trans('Reference') }}
                    </label>
                    <div class="mt-1">
                        <PureInput :modelValue="selectedBrandToUpdate?.reference"
                            @update:modelValue="(e) => set(selectedBrandToUpdate, ['reference'], e)"
                            placeholder="1-16 characters" />
                    </div>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium leading-6">
                        <span class="text-red-500">*</span> {{ trans('Name') }}
                    </label>
                    <div class="mt-1">
                        <PureInput :modelValue="selectedBrandToUpdate?.name"
                            @update:modelValue="(e) => set(selectedBrandToUpdate, ['name'], e)"
                            placeholder="1-64 characters" />
                    </div>
                </div>
            </div>

            <div class="mt-6 mb-4 relative">
                <Button @click="() => onEditBrand()" label="Edit Brand"
                    :disabled="!selectedBrandToUpdate?.reference || !selectedBrandToUpdate?.name"
                    :loading="isLoadingUpdateBrand" full />
            </div>
        </div>
    </Modal>
</template>
