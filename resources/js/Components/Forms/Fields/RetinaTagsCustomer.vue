<script setup lang="ts">
import { onMounted, ref, computed, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { routeType } from '@/types/route'
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

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

// state
const isLoadingMultiselect = ref(false)
const optionsList = ref<any[]>([])
const search = ref('')
const pagination = ref<any>(null)

// fetch tags
const fetchTags = async (url?: string) => {
    isLoadingMultiselect.value = true

    const urlToFetch = url || route(
        props.fieldData?.tag_routes?.index_tag.name,
        props.fieldData?.tag_routes?.index_tag.parameters
    )

    try {
        const res = await axios.get(urlToFetch)

        if (url) {
            optionsList.value = [...optionsList.value, ...res.data.data]
        } else {
            optionsList.value = res.data.data
        }

        pagination.value = res.data.meta
    } catch {
        notify({
            title: trans('Something went wrong.'),
            text: trans('Failed to fetch tag list'),
            type: 'error'
        })
    }

    isLoadingMultiselect.value = false
}

// selected tags
const formSelectedTags = computed({
    get: () => props.form[props.fieldName] || [],
    set: (val) => {
        props.form[props.fieldName] = val
    }
})

// attach
const onAttachTag = async (tagId: number) => {
    props.form[props.fieldName] = [
        ...(props.form[props.fieldName] || []),
        tagId
    ]

    if (!props.fieldData?.tag_routes?.attach_tag?.name) return

    try {
        await axios.post(
            route(
                props.fieldData.tag_routes.attach_tag.name,
                props.fieldData.tag_routes.attach_tag.parameters
            ),
            { tags_id: [tagId] }
        )
    } catch (error) {
        props.form[props.fieldName] =
            (props.form[props.fieldName] || []).filter((id: number) => id !== tagId)

        notify({
            title: trans('Error'),
            text: trans('Failed to attach tag'),
            type: 'error'
        })
    }
}

// detach
const onDetachTag = async (tagId: number) => {
    // optimistic update
    props.form[props.fieldName] =
        (props.form[props.fieldName] || []).filter((id: number) => id !== tagId)

    if (!props.fieldData?.tag_routes?.detach_tag?.name) return

    try {
        await axios.delete(
            route(
                props.fieldData.tag_routes.detach_tag.name,
                {
                    ...props.fieldData.tag_routes.detach_tag.parameters,
                    tag: tagId
                }
            )
        )
    } catch (error) {
        props.form[props.fieldName] = [
            ...(props.form[props.fieldName] || []),
            tagId
        ]

        notify({
            title: trans('Error'),
            text: trans('Failed to detach tag'),
            type: 'error'
        })
    }
}

// toggle
const toggleTag = (tagId: number) => {
    const current = props.form[props.fieldName] || []

    if (current.includes(tagId)) {
        onDetachTag(tagId)
    } else {
        onAttachTag(tagId)
    }
}

// filter
const filteredOptions = computed(() => {
    if (!search.value) return optionsList.value

    return optionsList.value.filter((tag) =>
        tag.name.toLowerCase().includes(search.value.toLowerCase())
    )
})

// fallback
watch(() => props.fieldData?.tags, (newTags) => {
    if (!optionsList.value.length) {
        optionsList.value = newTags || []
    }
})

// init
onMounted(() => {
    fetchTags()
})
</script>

<template>
    <div class="w-full max-w-md">

        <!-- Search -->
        <div class="mb-3">
            <input
                v-model="search"
                type="text"
                placeholder="Search tags..."
                class="w-full px-3 py-1.5 text-xs rounded-md bg-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 transition"
            />
        </div>

        <!-- Loading -->
        <div v-if="isLoadingMultiselect" class="flex justify-center py-4">
            <LoadingIcon />
        </div>

        <!-- Options -->
        <div v-else class="flex flex-wrap gap-2">

            <label
                v-for="tag in filteredOptions"
                :key="tag.id"
                class="flex items-center gap-2 cursor-pointer px-2 py-1 rounded-md text-xs transition border"
                :class="formSelectedTags.includes(tag.id)
                    ? 'bg-blue-50 border-gray-400'
                    : 'bg-white border-gray-200 hover:bg-gray-50'"
            >
                <!-- Checkbox -->
                <input
                    type="checkbox"
                    class="w-3.5 h-3.5 accent-blue-600"
                    :checked="formSelectedTags.includes(tag.id)"
                    @change="toggleTag(tag.id)"
                />

                <!-- Label -->
                <span class="text-gray-700 leading-none">
                    {{ tag.name }}
                </span>
            </label>

        </div>

        <!-- Empty -->
        <div
            v-if="!filteredOptions.length && !isLoadingMultiselect"
            class="text-center text-gray-400 py-4 text-xs"
        >
            {{ ctrans("No tags found") }}
        </div>

        <!-- Load More -->
        <div v-if="pagination?.next_page_url" class="mt-3 text-center">
            <button
                @click="fetchTags(pagination.next_page_url)"
                class="text-xs text-blue-600 hover:underline"
            >
                {{ ctrans("Load more") }}
            </button>
        </div>

    </div>
</template>