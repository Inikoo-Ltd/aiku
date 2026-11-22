<script setup lang="ts">
import { ref } from "vue"
import { router, usePage } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPencil, faTimes, faCheck } from "@fal"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

interface TextTranslation {
    id: number
    locale: string
    source_text: string
    text: string
    origin: "machine" | "human"
}

defineProps<{
    data: object
    tab?: string
}>()

const updateRoute = usePage().props.text_translation_route as routeType

const editing = ref<TextTranslation | null>(null)
const draft = ref("")
const error = ref<string | null>(null)
const isSaving = ref(false)

const open = (row: TextTranslation) => {
    editing.value = row
    draft.value = row.text
    error.value = null
}

const save = () => {
    if (!editing.value) return
    isSaving.value = true
    router.patch(
        route(updateRoute.name, { ...updateRoute.parameters, websiteTextTranslation: editing.value.id }),
        { text: draft.value },
        {
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
            onError: (errors) => (error.value = errors.text ?? Object.values(errors)[0] ?? null),
            onFinish: () => (isSaving.value = false),
        }
    )
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(source_text)="{ item }: { item: TextTranslation }">
            <div class="line-clamp-2 max-w-md text-gray-500">{{ item.source_text }}</div>
        </template>
        <template #cell(text)="{ item }: { item: TextTranslation }">
            <div class="line-clamp-2 max-w-md" :lang="item.locale">{{ item.text }}</div>
        </template>
        <template #cell(origin)="{ item }: { item: TextTranslation }">
            <span class="whitespace-nowrap text-xs" :class="item.origin === 'human' ? 'text-green-600' : 'text-gray-400'">
                {{ item.origin === "human" ? ctrans("Staff") : ctrans("AI") }}
            </span>
        </template>
        <template #cell(actions)="{ item }: { item: TextTranslation }">
            <Button type="tertiary" size="xs" :label="ctrans('Edit')" @click="open(item)">
                <template #icon>
                    <FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </template>
    </Table>

    <Dialog :visible="!!editing" modal :header="ctrans('Correct translation')" :style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false" @update:visible="(v) => !v && (editing = null)">
        <div v-if="editing" class="space-y-3 text-sm">
            <div>
                <div class="mb-1 text-xs text-gray-500">{{ ctrans("Original") }}</div>
                <div class="max-h-40 overflow-auto whitespace-pre-wrap rounded border border-gray-200 bg-gray-50 p-2 text-gray-600">{{ editing.source_text }}</div>
            </div>
            <div>
                <div class="mb-1 text-xs text-gray-500">{{ ctrans("Translation") }} ({{ editing.locale }})</div>
                <Textarea v-model="draft" :lang="editing.locale" autoResize rows="3" class="w-full" />
                <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
            </div>
        </div>
        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="editing = null">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="ctrans('Save')" :loading="isSaving" :disabled="!draft.trim()" @click="save">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
