<script setup lang="ts">
import { computed, ref } from "vue"
import { InputText } from "primevue"
import Chip from "primevue/chip"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"

const props = withDefaults(defineProps<{
    max?: number
    disabled?: boolean
    invalid?: boolean
    inputId?: string
}>(), {
    max: 3,
})

const keywords = defineModel<string[]>({ required: true })

const draft = ref("")

const isFull = computed(() => keywords.value.length >= props.max)

const add = () => {
    const typed = draft.value.split(",").map((keyword) => keyword.trim()).filter(Boolean)
    const known = new Set(keywords.value.map((keyword) => keyword.toLowerCase()))
    const fresh = typed.filter((keyword) => !known.has(keyword.toLowerCase()) && known.add(keyword.toLowerCase()))

    keywords.value = [...keywords.value, ...fresh].slice(0, props.max)
    draft.value = ""
}

const remove = (keyword: string) => {
    keywords.value = keywords.value.filter((existing) => existing !== keyword)
}

const chipPt = {
    root: { class: "!rounded-full !border !border-[--app-accent-muted] !bg-[--app-accent-soft] !px-3 !py-1 !text-xs !font-medium !text-[--app-accent-strong]" },
    removeIcon: { class: "!text-[--app-accent-strong] hover:opacity-70" },
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex gap-2">
            <InputText
                :id="inputId"
                v-model="draft"
                class="flex-1"
                :placeholder="ctrans('e.g. electric aroma diffuser, essential oil nebuliser')"
                :disabled="disabled || isFull"
                :invalid="invalid"
                @keydown.enter.prevent="add" />
            <Button type="tertiary" :label="ctrans('Add')" :disabled="disabled || isFull || !draft.trim()" @click="add">
                <template #icon>
                    <FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>

        <div v-if="keywords.length" class="flex flex-wrap gap-2">
            <Chip v-for="keyword in keywords" :key="keyword" :label="keyword" :removable="!disabled" :pt="chipPt" @remove="remove(keyword)" />
        </div>

        <p class="text-xs" :class="isFull ? 'font-medium text-green-700' : 'text-gray-500'">
            {{ ctrans(":count of :max added", { count: String(keywords.length), max: String(max) }) }}<template v-if="isFull"> · {{ ctrans("maximum reached") }}</template>
        </p>
    </div>
</template>
