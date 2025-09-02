<script setup lang="ts">
import MultiSelect from 'primevue/multiselect'
import { onMounted, ref, computed, watch } from 'vue'
import Modal from '@/Components/Utils/Modal.vue'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { notify } from '@kyvg/vue3-notification'
import Tag from '@/Components/Tag.vue'
import axios from 'axios'
import { set } from 'lodash-es'
import PureImageCrop from '@/Components/Pure/PureImageCrop.vue'
import { routeType } from '@/types/route'
import { faPlus } from '@fas'
import { faTrashAlt } from '@fal'

library.add(faPlus, faTrashAlt)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        tag_routes?: {
            index_tag: routeType
            store_tag: routeType
            update_tag: routeType
        }
        tags: {}[]
    }
}>()

// Modal create new tag
const isModalTag = ref(false)
const _multiselect_tags = ref(null)
const newTagImg = ref<File | null>(null)
const newTagName = ref('')
const isLoadingCreateTag = ref(false)

const onCreateNewTag = () => {
    router.post(
        route(
            props.fieldData?.tag_routes?.store_tag.name,
            props.fieldData?.tag_routes?.store_tag.parameters
        ),
        {
            name: newTagName.value,
            image: newTagImg.value,
        },
        {
            onStart: () => {
                isLoadingCreateTag.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans('Success'),
                    text: trans('Successfully created new tag') + ': ' + newTagName.value,
                    type: 'success',
                })
                isModalTag.value = false
                newTagName.value = ''
            },
            onFinish: () => {
                isLoadingCreateTag.value = false
            },
            onError: (error) => {
                notify({
                    title: trans('Something went wrong'),
                    text: error.message,
                    type: 'error',
                })
            },
        }
    )
}

// Fetch tags
const isLoadingMultiselect = ref(false)
const optionsList = ref<any[]>([])

const fetchProductList = async (url?: string) => {
    isLoadingMultiselect.value = true
    const urlToFetch =
        url ||
        route(
            props.fieldData?.tag_routes?.index_tag.name,
            props.fieldData?.tag_routes?.index_tag.parameters
        )

    try {
        const res = await axios.get(urlToFetch)
        optionsList.value = res?.data?.data
    } catch {
        notify({
            title: trans('Something went wrong.'),
            text: trans('Failed to fetch tag list'),
            type: 'error',
        })
    }
    isLoadingMultiselect.value = false
}

// Attach/Detach tags directly on parent form
const formSelectedTags = computed({
    get: () => props.form[props.fieldName] || [],
    set: (val) => {
        props.form[props.fieldName] = val
    },
})

watch(
    () => props.fieldData?.tags,
    (newTags) => {
        optionsList.value = newTags || []
    }
)

// Update tag
const isModalUpdateTag = ref(false)
const isLoadingUpdateTag = ref(false)
const selectedUpdateTag = ref<any>(null)

const onEditTag = () => {
    router[
        props.fieldData?.tag_routes?.update_tag.method || 'patch'
    ](
        route(props.fieldData?.tag_routes?.update_tag.name, {
            ...props.fieldData?.tag_routes?.update_tag.parameters,
            tag: selectedUpdateTag.value?.id,
        }),
        {
            name: selectedUpdateTag.value?.name,
            image: selectedUpdateTag.value?.image,
        },
        {
            onStart: () => {
                isLoadingUpdateTag.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans('Success!'),
                    text: trans('Successfully updated tag.'),
                    type: 'success',
                })
                isModalUpdateTag.value = false
                selectedUpdateTag.value = null
            },
            onFinish: () => {
                isLoadingUpdateTag.value = false
            },
            onError: (error) => {
                console.error('Error editing tag:', error)
                notify({
                    title: trans('Something went wrong'),
                    text: error.message || trans('Failed to update tag'),
                    type: 'error',
                })
            },
        }
    )
}

// Utility: find tag by ID safely
const findTagById = (id: number) => {
    return optionsList.value.find((t) => t.id === id)
}

onMounted(() => {
    fetchProductList()
})
</script>

<template>
    <div class="w-full max-w-md py-4">
        <div>Tags:</div>

        <!-- Tags list (sync with form value) -->
        <div v-if="formSelectedTags.length" class="flex flex-wrap mb-2 gap-x-2 gap-y-1">
            <Tag v-for="tagId in formSelectedTags" :key="tagId" :label="findTagById(tagId)?.name" @click.self="() => {
                const found = findTagById(tagId)
                if (found) {
                    isModalUpdateTag = true
                    selectedUpdateTag = { ...found }
                }
            }" stringToColor style="cursor: pointer">
                <template #closeButton>
                    <div @click="formSelectedTags = formSelectedTags.filter((id) => id !== tagId)"
                        class="cursor-pointer bg-white/60 hover:bg-black/10 px-1 text-red-500 rounded-sm">
                        <FontAwesomeIcon icon="fal fa-trash-alt" class="text-xs" aria-hidden="true" />
                    </div>
                </template>
            </Tag>
        </div>

        <!-- Multiselect tags -->
        <div v-if="props.fieldData?.tag_routes?.index_tag?.name" class="w-full max-w-64">
            <MultiSelect ref="_multiselect_tags" v-model="formSelectedTags"
                :options="optionsList.length ? optionsList : props.fieldData?.tags" optionLabel="name" optionValue="id"
                placeholder="Select Tags" :maxSelectedLabels="3" filter class="w-full md:w-80">
                <template #footer="{ value, options }">
                    <div v-if="isLoadingMultiselect" class="absolute inset-0 bg-black/30 rounded flex justify-center items-center text-white text-4xl">
                        <LoadingIcon></LoadingIcon>
                    </div>

                    <div class="cursor-pointer border-t border-gray-300 p-2 flex flex-col gap-y-2 justify-center items-center text-center">
                        <Button
                            @click="() => (_multiselect_tags?.hide())"
                            :label="formSelectedTags.isDirty ? trans('Save and close') : trans('Close')"
                            xicon="fas fa-plus"
                            full
                            :key="`${formSelectedTags.isDirty}`"
                            :type="formSelectedTags.isDirty ? 'secondary' : 'tertiary'"
                        />
                        
                        <Button
                            @click="() => (isModalTag = true, _multiselect_tags?.hide())"
                            :label="trans('Create new tag')"
                            icon="fas fa-plus"
                            full
                            type="dashed"
                        />
                    </div>
                </template>
            </MultiSelect>
        </div>
    </div>

    <!-- Modal: create new tag -->
    <Modal :isOpen="isModalTag" @onClose="isModalTag = false" width="w-[600px]">
        <div class="isolate bg-white px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans('Create new tag') }}</h2>
            </div>

            <div class="mt-7 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <div class="col-span-2">
                    <label for="first-name" class="block text-sm font-medium leading-6">
                        {{ trans('Image') }}
                    </label>
                    <div class="mt-1">
                        <PureImageCrop :aspectRatio="1 / 1" @cropped="(e) => newTagImg = e" />
                    </div>
                </div>

                <div class="col-span-2">
                    <label for="first-name" class="block text-sm font-medium leading-6">
                        <span class="text-red-500">*</span> {{ trans('Name') }}
                    </label>
                    <div class="mt-1">
                        <PureInput v-model="newTagName" placeholder="1-64 characters" />
                    </div>
                </div>

            </div>

            <div class="mt-6 mb-4 relative">
                <Button @click="() => onCreateNewTag()" label="Submit" :disabled="!newTagName"
                    :loading="isLoadingCreateTag" full />
            </div>
        </div>
    </Modal>

    <!-- Modal: edit tag -->
    <Modal :isOpen="isModalUpdateTag" @onClose="isModalUpdateTag = false" width="w-[600px]">
        <div class="isolate bg-white px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-lg font-bold tracking-tight sm:text-2xl">
                    {{ trans('Edit tag') }}
                </h2>
            </div>

            <div class="mt-7 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <div class="col-span-2">
                    <label class="block text-sm font-medium leading-6">{{
                        trans('Image')
                        }}</label>
                    <div class="mt-1">
                        <PureImageCrop :src_image="selectedUpdateTag?.image" :aspectRatio="1 / 1"
                            @cropped="(e) => (selectedUpdateTag.image = e)" />
                    </div>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium leading-6">
                        <span class="text-red-500">*</span> {{ trans('Name') }}
                    </label>
                    <div class="mt-1">
                        <PureInput :modelValue="selectedUpdateTag?.name"
                            @update:modelValue="(e) => set(selectedUpdateTag, ['name'], e)"
                            placeholder="1-64 characters" />
                    </div>
                </div>
            </div>

            <div class="mt-6 mb-4 relative">
                <Button @click="() => onEditTag()" label="Update tag" :disabled="!selectedUpdateTag?.name"
                    :loading="isLoadingUpdateTag" full />
            </div>
        </div>
    </Modal>
</template>
