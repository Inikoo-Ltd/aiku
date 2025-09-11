<script setup lang="ts">
import MultiSelect from 'primevue/multiselect'
import { onMounted, ref, computed, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import Tag from '@/Components/Tag.vue'
import axios from 'axios'
import { routeType } from '@/types/route'
import { faPlus } from '@fas'
import { faTrashAlt } from '@fal'

library.add(faPlus, faTrashAlt)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        options_routes?: {
            index_tag: routeType
            store_tag: routeType
            update_tag: routeType
        }
        tags: {}[]
    }
}>()

// Modal create new tag
const _multiselect_tags = ref(null)


// Fetch tags
const isLoadingMultiselect = ref(false)
const optionsList = ref<any[]>([])

const fetchProductList = async (url?: string) => {
    isLoadingMultiselect.value = true
    const urlToFetch =
        url ||
        route(
            props.fieldData?.options_routes.name,
            props.fieldData?.options_routes.parameters
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
        <!-- Tags list (sync with form value) -->
        <div v-if="formSelectedTags.length" class="flex flex-wrap mb-2 gap-x-2 gap-y-1">
            <Tag v-for="tagId in formSelectedTags" :key="tagId" :label="findTagById(tagId)?.name" stringToColor
                style="cursor: pointer">
                <template #closeButton>
                    <div @click="formSelectedTags = formSelectedTags.filter((id) => id !== tagId)"
                        class="cursor-pointer bg-white/60 hover:bg-black/10 px-1 text-red-500 rounded-sm">
                        <FontAwesomeIcon icon="fal fa-trash-alt" class="text-xs" aria-hidden="true" />
                    </div>
                </template>
            </Tag>
        </div>

        <!-- Multiselect tags -->
        <div v-if="props.fieldData?.options_routes?.name" class="w-full max-w-64">
            <MultiSelect ref="_multiselect_tags" v-model="formSelectedTags"
                :options="optionsList.length ? optionsList : props.fieldData?.tags" optionLabel="name" optionValue="id"
                placeholder="Select Tags" :maxSelectedLabels="3" filter class="w-full md:w-80">
                <template #footer="{ value, options }">
                    <div v-if="isLoadingMultiselect"
                        class="absolute inset-0 bg-black/30 rounded flex justify-center items-center text-white text-4xl">
                        <LoadingIcon></LoadingIcon>
                    </div>
                    <div
                        class="cursor-pointer border-t border-gray-300 p-2 flex flex-col gap-y-2 justify-center items-center text-center">
                        <Button @click="() => (_multiselect_tags?.hide())"
                            :label="formSelectedTags.isDirty ? trans('Save and close') : trans('Close')"
                            xicon="fas fa-plus" full :key="`${formSelectedTags.isDirty}`"
                            :type="formSelectedTags.isDirty ? 'secondary' : 'tertiary'" />
                    </div>
                </template>
            </MultiSelect>
        </div>
    </div>
</template>
