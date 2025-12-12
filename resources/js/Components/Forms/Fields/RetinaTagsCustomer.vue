<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Fri, 07 Nov 2025 13:35:07 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import MultiSelect from 'primevue/multiselect'
import { onMounted, ref, computed, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import Tag from '@/Components/Tag.vue'
import axios from 'axios'
import { routeType } from '@/types/route'
import { faPlus } from '@fas'
import { faTrashAlt } from '@fal'
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'

library.add(faPlus, faTrashAlt)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        tag_routes?: {
            index_tag: routeType
            attach_tag: routeType
            detach_tag: routeType
        }
        tags: {}[]
    }
}>()

const _multiselect_tags = ref(null)

// Fetch tags
const isLoadingMultiselect = ref(false)

const optionsList = ref<any[]>([])

const fetchTags = async (url?: string) => {
    isLoadingMultiselect.value = true

    const urlToFetch = url || route(props.fieldData?.tag_routes?.index_tag.name, props.fieldData?.tag_routes?.index_tag.parameters)

    try {
        const res = await axios.get(urlToFetch)

        optionsList.value = res?.data?.data
    } catch {
        notify({
            title: trans('Something went wrong.'),
            text: trans('Failed to fetch tag list'),
            type: 'error'
        })
    }

    isLoadingMultiselect.value = false
}

// Attach/Detach tags directly on parent form
const formSelectedTags = computed({
    get: () => props.form[props.fieldName] || [],
    set: (newVal) => {
        const oldVal = props.form[props.fieldName] || []

        // Find newly added tag (user checked a tag)
        const addedTags = newVal.filter((id: number) => !oldVal.includes(id))

        // Prevent removal via multiselect - only allow adding
        if (addedTags.length > 0) {
            // Attach the new tag (single tag)
            onAttachTag(addedTags[0])
        } else {
            // If user tried to uncheck, revert to old value
            props.form[props.fieldName] = oldVal
        }
    }
})

watch(() => props.fieldData?.tags, (newTags) => { optionsList.value = newTags || [] })

// Utility: find tag by ID safely
const findTagById = (id: number) => {
    return optionsList.value.find((t) => t.id === id)
}

// Attach single tag function
const onAttachTag = (tagId: number) => {
    if (!props.fieldData?.tag_routes?.attach_tag?.name) {
        // Fallback: just update form value if no attach route
        props.form[props.fieldName] = [...(props.form[props.fieldName] || []), tagId]
        return
    }

    router[props.fieldData.tag_routes.attach_tag.method || 'post'](
        route(props.fieldData.tag_routes.attach_tag.name, props.fieldData.tag_routes.attach_tag.parameters), { tags_id: [tagId] }
    )
}

onMounted(() => {
    fetchTags()
})
</script>

<template>
    <div class="w-full max-w-md ">

        <!-- Tags list (sync with form value) -->
        <div v-if="formSelectedTags.length" class="flex flex-wrap mb-2 gap-x-2 gap-y-1">
            <Tag
                v-for="tagId in formSelectedTags"
                :key="tagId"
                :label="findTagById(tagId)?.name"
                stringToColor
                style="cursor: pointer"
            >
                <template #closeButton>
                    <ModalConfirmationDelete
                        :routeDelete="{ name: fieldData?.tag_routes?.detach_tag.name ?? '', parameters: { ...fieldData?.tag_routes?.detach_tag.parameters, tag: tagId } }"
                        :title="trans('Are you sure you want to detach this tag?')"
                        :description="trans('This tag will be removed from this item.')"
                        :noLabel="trans('Detach')"
                        noIcon="fal fa-trash-alt"
                    >
                        <template #default="{ changeModel }">
                            <div @click="changeModel" class="cursor-pointer bg-white/60 hover:bg-black/10 px-1 text-red-500 rounded-sm">
                                <FontAwesomeIcon icon="fal fa-trash-alt" class="text-xs" aria-hidden="true" />
                            </div>
                        </template>
                    </ModalConfirmationDelete>
                </template>
            </Tag>
        </div>

        <!-- Multiselect tags -->
        <div v-if="props.fieldData?.tag_routes?.index_tag?.name" class="w-full max-w-64">
            <MultiSelect
                ref="_multiselect_tags"
                v-model="formSelectedTags"
                :options="optionsList.length ? optionsList : props.fieldData?.tags"
                optionLabel="name"
                optionValue="id"
                :optionDisabled="(option) => formSelectedTags.includes(option.id)"
                :placeholder="trans('Select tags')"
                :maxSelectedLabels="3"
                filter
                class="w-full md:w-80"
                :showClear="false"
            >
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
                    </div>
                </template>
            </MultiSelect>
        </div>
    </div>
</template>
