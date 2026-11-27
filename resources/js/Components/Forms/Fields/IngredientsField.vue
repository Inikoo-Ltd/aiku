<script setup lang="ts">
import { ref, computed } from "vue"
import axios from "axios"
import draggable from "vuedraggable"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faGripVertical } from "@fas"
import { ctrans } from "@/Composables/useTrans"
import SelectInfiniteScroll from "@/Components/Forms/Fields/SelectInfiniteScroll.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { routeType } from "@/types/route"

type ParsedIngredient = { name: string; slug: string | null; is_new: boolean }

const props = defineProps<{
    form: any
    fieldName: string
    options: { slug: string; name: string }[]
    fieldData: {
        fetchRoute: routeType
        parseRoute: routeType
        [key: string]: any
    }
    submit?: () => void
}>()

const localOptions = ref([...(props.options ?? [])])
const selectRemountKey = ref(0)
const isModalOpen = ref(false)
const pastedText = ref("")
const preview = ref<ParsedIngredient[] | null>(null)
const isLoading = ref(false)
const parseError = ref<string | null>(null)

const rememberOptions = (options: { slug: string; name: string }[]) => {
    for (const option of options ?? []) {
        if (!localOptions.value.some((known) => known.slug === option.slug)) {
            localOptions.value.push({ slug: option.slug, name: option.name })
        }
    }
}

const ingredientName = (slug: string) => localOptions.value.find((option) => option.slug === slug)?.name ?? slug

const orderedSlugs = computed({
    get: () => props.form[props.fieldName] ?? [],
    set: (slugs: string[]) => {
        props.form[props.fieldName] = slugs
        selectRemountKey.value++
    },
})

const parse = async (commit: boolean, replace = false) => {
    isLoading.value = true
    parseError.value = null
    try {
        const response = await axios.post(
            route(props.fieldData.parseRoute.name, props.fieldData.parseRoute.parameters),
            { text: pastedText.value, commit }
        )

        if (!commit) {
            preview.value = response.data
            return
        }

        const parsed: ParsedIngredient[] = response.data
        const selected = new Set(replace ? [] : props.form[props.fieldName] ?? [])

        for (const ingredient of parsed) {
            if (!ingredient.slug) {
                continue
            }
            rememberOptions([{ slug: ingredient.slug, name: ingredient.name }])
            selected.add(ingredient.slug)
        }

        props.form[props.fieldName] = [...selected]
        selectRemountKey.value++
        closeModal()
        props.submit?.()
    } catch (error: any) {
        parseError.value = error.response?.data?.message ?? ctrans("Failed to parse the ingredients")
    } finally {
        isLoading.value = false
    }
}

const closeModal = () => {
    isModalOpen.value = false
    pastedText.value = ""
    preview.value = null
    parseError.value = null
}
</script>

<template>
    <div>
        <SelectInfiniteScroll
            :key="selectRemountKey"
            :form="form"
            :fieldName="fieldName"
            :options="localOptions"
            :fieldData="fieldData"
            @optionsList="rememberOptions"
        />

        <div v-if="orderedSlugs.length > 1" class="mt-3">
            <div class="text-xs text-gray-500 mb-1">
                {{ ctrans("Order shown on product pages and labels. Drag to change it.") }}
            </div>
            <draggable v-model="orderedSlugs" :item-key="(slug: string) => slug" handle=".drag-handle" :animation="150" tag="ol" class="space-y-1">
                <template #item="{ element: slug, index }">
                    <li class="drag-handle flex items-center gap-2 rounded border border-gray-200 bg-white px-2 py-1 text-sm cursor-move">
                        <FontAwesomeIcon :icon="faGripVertical" class="text-gray-400" fixed-width aria-hidden="true" />
                        <span class="w-6 text-right text-gray-400 tabular-nums">{{ index + 1 }}.</span>
                        <span>{{ ingredientName(slug) }}</span>
                    </li>
                </template>
            </draggable>
        </div>

        <div class="mt-2">
            <Button
                type="tertiary"
                size="xs"
                icon="fal fa-paste"
                :label="ctrans('Paste list')"
                @click="isModalOpen = true"
            />
        </div>

        <Modal :isOpen="isModalOpen" @onClose="closeModal" width="w-full max-w-2xl">
            <div class="text-left">
                <div class="text-lg mb-2">{{ ctrans("Paste ingredients") }}</div>
                <div class="text-sm text-gray-500 mb-3">
                    {{ ctrans("Separated by commas or new lines. Trailing asterisks are ignored.") }}
                </div>

                <textarea
                    v-model="pastedText"
                    rows="6"
                    class="w-full rounded border-gray-300 text-sm"
                    :placeholder="ctrans('Aqua, Glycerin, Parfum, Linalool*')"
                    @input="preview = null"
                />

                <div v-if="preview" class="mt-3">
                    <div class="text-sm text-gray-500 mb-1">
                        {{ ctrans("Preview") }}: {{ preview.length }}
                        <span v-if="preview.some((ingredient) => ingredient.is_new)">
                            ({{ preview.filter((ingredient) => ingredient.is_new).length }} {{ ctrans("new") }})
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1 max-h-60 overflow-y-auto">
                        <span
                            v-for="ingredient in preview"
                            :key="ingredient.name"
                            class="px-2 py-0.5 rounded text-sm"
                            :class="ingredient.is_new ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700'"
                        >
                            {{ ingredient.name }}
                        </span>
                    </div>
                </div>

                <p v-if="parseError" class="mt-2 text-xs text-red-600">{{ parseError }}</p>

                <div class="mt-4 flex items-center justify-end gap-2 whitespace-nowrap">
                    <div v-if="preview && (form[fieldName] ?? []).length" class="mr-auto text-xs text-gray-500 whitespace-normal">
                        {{
                            ctrans("Replace all removes the :count ingredient(s) currently selected. Add to current keeps them.", {
                                count: (form[fieldName] ?? []).length,
                            })
                        }}
                    </div>

                    <Button
                        v-if="!preview"
                        :label="ctrans('Next')"
                        :disabled="!pastedText.trim()"
                        :loading="isLoading"
                        @click="parse(false)"
                    />
                    <template v-else>
                        <Button
                            type="tertiary"
                            icon="fad fa-save"
                            :label="ctrans('Replace all')"
                            :loading="isLoading"
                            @click="parse(true, true)"
                        />
                        <Button
                            icon="fad fa-save"
                            :label="ctrans('Add to current')"
                            :loading="isLoading"
                            @click="parse(true)"
                        />
                    </template>
                </div>
            </div>
        </Modal>
    </div>
</template>
