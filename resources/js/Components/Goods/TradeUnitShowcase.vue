<script setup lang="ts">
import { routeType } from '@/types/route'
import MultiSelect from 'primevue/multiselect';
import { ref } from 'vue'
import Modal from '@/Components/Utils/Modal.vue'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from '../Elements/Buttons/Button.vue'
import PureInput from '../Pure/PureInput.vue'
import { notify } from '@kyvg/vue3-notification'
import Tag from '../Tag.vue'
import PureMultiselectInfiniteScroll from '../Pure/PureMultiselectInfiniteScroll.vue'
import axios from 'axios'
import { set } from 'lodash'
library.add(faPlus)

const props = defineProps<{
    data: {
        tag_routes: {
            index_tag: routeType
            store_tag: routeType
            update_tag: routeType
            destroy_tag: routeType
            attach_tag: routeType
            detach_tag: routeType
        }
        tags: {}[]
        tags_selected_id: number[]
    }
}>()

console.log('props', props.data.tag_routes)

const isModalTag = ref(false)
const _multiselect_tags = ref(null)
const newTagName = ref('')
const isLoadingCreateTag = ref(false)
const onCreateNewTag = () => {
    router.post(
        route(props.data.tag_routes.store_tag.name, props.data.tag_routes.store_tag.parameters),
        {
            name: newTagName.value,
        },
        {
            onStart: () => {
                isLoadingCreateTag.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully created new tag") + ': ' + newTagName.value,
                    type: "success",
                })
                isModalTag.value = false
                newTagName.value = ''
                // selectedTags.value.push(response.data.id)
            },
            onFinish: () => {
                isLoadingCreateTag.value = false
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

// Section: fetch index tag
const isLoadingMultiselect = ref<string | boolean>(false)
const optionsList = ref<any[]>([])
const fetchProductList = async (url?: string) => {
    isLoadingMultiselect.value = true

    const urlToFetch = url || route(props.data.tag_routes.index_tag.name, props.data.tag_routes.index_tag.parameters)

    try {
        const xxx = await axios.get(urlToFetch)
        
        optionsList.value = [...optionsList.value, ...xxx?.data?.data]

        console.log('fetch xxx', xxx)

    } catch  {
        // console.log(error)
        notify({
            title: trans('Something went wrong.'),
            text: trans('Failed to fetch product list'),
            type: 'error',
        })
    }
    isLoadingMultiselect.value = false
}

// Section: manage attach-detach tag
const onManageTags = () => {
    router.post(
        route(props.data.tag_routes.attach_tag.name, props.data.tag_routes.attach_tag.parameters),
        {
            tags_id: props.data.tags_selected_id,
        },
        {
            onStart: () => {
                isLoadingCreateTag.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully update tag for this unit"),
                    type: "success",
                })
                isModalTag.value = false
                newTagName.value = ''
                // selectedTags.value.push(response.data.id)
            },
            onFinish: () => {
                isLoadingCreateTag.value = false
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


// Section: update tag
const isModalUpdateTag = ref(false)
const isLoadingUpdateTag = ref(false)
const selectedUpdateTag = ref<any>(null)
const onEditTag = () => {
    router[props.data.tag_routes.update_tag.method || 'patch'](
        route(props.data.tag_routes.update_tag.name, {
            ...props.data.tag_routes.update_tag.parameters,
            tag: selectedUpdateTag.value?.slug,
        }),
        {
            name: selectedUpdateTag.value?.name,
        },
        {
            onStart: () => {
                isLoadingUpdateTag.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success!"),
                    text: trans("Successfully update tag name."),
                    type: "success",
                })
                isModalUpdateTag.value = false
                selectedUpdateTag.value = null
            },
            onFinish: () => {
                isLoadingUpdateTag.value = false
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
</script>

<template>
    <div>


        <div class="w-full max-w-md px-8 py-4">
            <div for="">
                Tags:
            </div>
            <div class="flex flex-wrap mb-2 gap-x-2">
                <Tag v-for="tag in data.tags" :label="tag.name" @click="() => (isModalUpdateTag = true, selectedUpdateTag = {...tag})" stringToColor class="cursor-pointer">
                </Tag>
            </div>

            <div class="w-full max-w-64">
                <!-- <PureMultiselectInfiniteScroll
                    xv-model="formAddService.service_id"
                    :fetchRoute="props.data.tag_routes.index_tag"
                    :placeholder="trans('Select Services')"
                    valueProp="id"
                    aoptionsList="(options) => dataServiceList = options"
                >
                    <template #singlelabel="{ value }">
                        <div class="w-full text-left pl-4">{{ value.tag_name }}</div>
                    </template>
                    <template #option="{ option, isSelected, isPointed }">
                        <div class="">{{ option.tag_name }} </div>
                    </template>
                    <template #afterlist>
                        <div class="cursor-pointer border-t border-gray-300 p-2 flex justify-center items-center text-center">
                        <Button
                            @click="() => (isModalTag = true, _multiselect_tags?.hide())"
                            label="Create new tag"
                            icon="fas fa-plus"
                            full
                            type="secondary"
                        />
                    </div>
                    </template>
                </PureMultiselectInfiniteScroll> -->
                
                <MultiSelect
                    ref="_multiselect_tags"
                    v-model="props.data.tags_selected_id"
                    :options="optionsList.length ? optionsList : props.data.tags"
                    :optionLabel="optionsList.length ? 'tag_name' : 'name'"
                    optionValue="id"
                    placeholder="Select Tags"
                    :maxSelectedLabels="3"
                    class="w-full md:w-80"
                    @show="() => optionsList.length ? null : fetchProductList()"
                    @hide="() => (onManageTags())"
                >
                    <template #footer="{ value, options }">
                        <div class="cursor-pointer border-t border-gray-300 p-2 flex justify-center items-center text-center">
                            <Button
                                @click="() => (isModalTag = true, _multiselect_tags?.hide())"
                                label="Create new tag"
                                icon="fas fa-plus"
                                full
                                type="secondary"
                            />
                        </div>
                    </template>
                </MultiSelect>
            </div>
        </div>

        
        <!-- <pre>{{ data.tags }}</pre> -->
        <!-- selected id: {{ props.data.tags_selected_id }}
        <pre>{{ optionsList }}</pre> -->

        <!-- Modal: create new tag -->
        <Modal :isOpen="isModalTag" @onClose="isModalTag = false" width="w-[600px]">
            <div class="isolate bg-white px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans('Create new tag') }}</h2>
                    <!-- <p class="text-xs leading-5 text-gray-400">
                        {{ trans('Information about payment from customer') }}
                    </p> -->
                </div>

                <div class="mt-7 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
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
                    <Button
                        @click="() => onCreateNewTag()"
                        label="Submit"
                        :disabled="!newTagName"
                        :loading="isLoadingCreateTag"
                        full
                    />

                    <!-- <Transition name="spin-to-down">
                        <p v-if="errorPaymentMethod" class="absolute text-red-500 italic text-sm mt-1">*{{
                            errorPaymentMethod }}</p>
                    </Transition> -->
                </div>
            </div>
        </Modal>

        <!-- Modal: manage tag -->
        <Modal :isOpen="isModalUpdateTag" @onClose="isModalUpdateTag = false" width="w-[600px]">
            <div class="isolate bg-white px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans('Edit tag') }}</h2>
                    <!-- <p class="text-xs leading-5 text-gray-400">
                        {{ trans('Information about payment from customer') }}
                    </p> -->
                </div>

                <div class="mt-7 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div class="col-span-2">
                        <label for="first-name" class="block text-sm font-medium leading-6">
                            <span class="text-red-500">*</span> {{ trans('Name') }}
                        </label>
                        <div class="mt-1">
                            <PureInput :modelValue="selectedUpdateTag?.name" @update:modelValue="(e) => set(selectedUpdateTag, ['name'], e)" placeholder="1-64 characters" />
                        </div>
                    </div>

                </div>

                <div class="mt-6 mb-4 relative">
                    <Button
                        @click="() => onEditTag()"
                        label="Update tag"
                        :disabled="!selectedUpdateTag?.name"
                        :loading="isLoadingUpdateTag"
                        full
                    />

                    <!-- <Transition name="spin-to-down">
                        <p v-if="errorPaymentMethod" class="absolute text-red-500 italic text-sm mt-1">*{{
                            errorPaymentMethod }}</p>
                    </Transition> -->
                </div>
            </div>
        </Modal>

        {{ selectedUpdateTag }}
    </div>
</template>