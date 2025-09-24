<script setup lang="ts">
import MultiSelect from 'primevue/multiselect'
import { ref, computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Tag from '@/Components/Tag.vue'
import { faPlus } from '@fas'
import { faTrashAlt } from '@fal'

library.add(faPlus, faTrashAlt)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        options?: any[] | Record<string, any>
        labelProp?: string
        valueProp?: string
        placeholder?: string
    }
}>()

// Modal ref
const _multiselect_tags = ref(null)

// Normalisasi options → selalu array
const normalizedOptions = computed(() => {
    const opts = props.fieldData?.options
    const labelProp = props.fieldData?.labelProp || 'name'
    const valueProp = props.fieldData?.valueProp || 'id'

    if (!opts) return []

    // Sudah array
    if (Array.isArray(opts)) return opts

    // Kalau object, ubah jadi array
    return Object.keys(opts).map(key => ({
        [valueProp]: opts[key][valueProp],
        [labelProp]: opts[key][labelProp],
    }))
})

// Attach/Detach options langsung di parent form
const formSelectedTags = computed({
    get: () => props.form[props.fieldName] || [],
    set: (val) => {
        props.form[props.fieldName] = val
    },
})

// Cari option by ID
const findTagById = (id: number | string) => {
    const valueProp = props.fieldData?.valueProp || 'id'
    return normalizedOptions.value.find((t) => String(t[valueProp]) === String(id))
}
</script>

<template>
    <div class="w-full max-w-md py-4">
        <!-- Selected tags -->
        <div v-if="formSelectedTags.length" class="flex flex-wrap mb-2 gap-x-2 gap-y-1">
            <Tag
                v-for="tagId in formSelectedTags"
                :key="tagId"
                :label="findTagById(tagId)?.[fieldData?.labelProp || 'name']"
                stringToColor
                style="cursor: pointer"
            >
                <template #closeButton>
                    <div
                        @click="formSelectedTags = formSelectedTags.filter((id) => id !== tagId)"
                        class="cursor-pointer bg-white/60 hover:bg-black/10 px-1 text-red-500 rounded-sm"
                    >
                        <FontAwesomeIcon icon="fal fa-trash-alt" class="text-xs" aria-hidden="true" />
                    </div>
                </template>
            </Tag>
        </div>

        <!-- Multiselect -->
        <div v-if="normalizedOptions.length" class="w-full max-w-64">
            <MultiSelect
                ref="_multiselect_tags"
                v-model="formSelectedTags"
                :options="normalizedOptions"
                :optionLabel="fieldData?.labelProp || 'name'"
                :optionValue="fieldData?.valueProp || 'id'"
                :placeholder="fieldData?.placeholder || trans('Select Tags')"
                :maxSelectedLabels="3"
                filter
                class="w-full md:w-80"
            >
                <template #footer>
                    <div class="cursor-pointer border-t border-gray-300 p-2 flex justify-center items-center text-center">
                        <Button
                            @click="() => (_multiselect_tags?.hide())"
                            :label="trans('Close')"
                            full
                            type="tertiary"
                        />
                    </div>
                </template>
            </MultiSelect>
        </div>

        <!-- Empty state -->
        <div v-else class="text-gray-400 text-sm italic">
            {{ trans('No options available') }}
        </div>
    </div>
</template>
