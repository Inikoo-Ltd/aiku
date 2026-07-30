<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
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
}>()

const localOptions = ref([...(props.options ?? [])])
const isModalOpen = ref(false)
const pastedText = ref("")
const preview = ref<ParsedIngredient[] | null>(null)
const isLoading = ref(false)

const parse = async (commit: boolean, replace = false) => {
    isLoading.value = true
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
            if (!localOptions.value.some((option) => option.slug === ingredient.slug)) {
                localOptions.value.push({ slug: ingredient.slug, name: ingredient.name })
            }
            selected.add(ingredient.slug)
        }

        props.form[props.fieldName] = [...selected]
        closeModal()
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error.response?.data?.message ?? trans("Failed to parse the ingredients"),
            type: "error",
        })
    } finally {
        isLoading.value = false
    }
}

const closeModal = () => {
    isModalOpen.value = false
    pastedText.value = ""
    preview.value = null
}
</script>

<template>
    <div>
        <SelectInfiniteScroll
            :form="form"
            :fieldName="fieldName"
            :options="localOptions"
            :fieldData="fieldData"
        />

        <div class="mt-2">
            <Button
                type="tertiary"
                size="xs"
                icon="fal fa-paste"
                :label="trans('Paste list')"
                @click="isModalOpen = true"
            />
        </div>

        <Modal :isOpen="isModalOpen" @onClose="closeModal" width="w-full max-w-2xl">
            <div class="text-left">
                <div class="text-lg mb-2">{{ trans("Paste ingredients") }}</div>
                <div class="text-sm text-gray-500 mb-3">
                    {{ trans("Separated by commas or new lines. Trailing asterisks are ignored.") }}
                </div>

                <textarea
                    v-model="pastedText"
                    rows="6"
                    class="w-full rounded border-gray-300 text-sm"
                    :placeholder="trans('Aqua, Glycerin, Parfum, Linalool*')"
                    @input="preview = null"
                />

                <div v-if="preview" class="mt-3">
                    <div class="text-sm text-gray-500 mb-1">
                        {{ trans("Preview") }}: {{ preview.length }}
                        <span v-if="preview.some((ingredient) => ingredient.is_new)">
                            ({{ preview.filter((ingredient) => ingredient.is_new).length }} {{ trans("new") }})
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

                <div class="mt-4 flex items-center justify-end gap-2 whitespace-nowrap">
                    <div v-if="preview" class="mr-auto text-xs text-gray-500 whitespace-normal">
                        {{
                            trans("Replace all removes the :count ingredient(s) currently selected. Add to current keeps them.", {
                                count: (form[fieldName] ?? []).length,
                            })
                        }}
                    </div>

                    <Button
                        v-if="!preview"
                        :label="trans('Next')"
                        :disabled="!pastedText.trim()"
                        :loading="isLoading"
                        @click="parse(false)"
                    />
                    <template v-else>
                        <Button
                            type="tertiary"
                            :label="trans('Replace all')"
                            :loading="isLoading"
                            @click="parse(true, true)"
                        />
                        <Button
                            :label="trans('Add to current')"
                            :loading="isLoading"
                            @click="parse(true)"
                        />
                    </template>
                </div>
            </div>
        </Modal>
    </div>
</template>
