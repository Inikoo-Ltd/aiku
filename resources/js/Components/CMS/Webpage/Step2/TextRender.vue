<script setup lang="ts">
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { computed, watch, ref } from "vue"
import { isPlainObject, cloneDeep, set } from 'lodash-es'

const props = withDefaults(defineProps<{
    modelValue: any
    webpageData: any
    blockData: any
    screenType?: string
}>(), {})

const emits = defineEmits<{
    (e: 'update:modelValue', value: any): void
}>()

const key = ref(1)

const valueForField = computed({
    get() {
        const raw = props.modelValue

        if (!isPlainObject(raw)) return raw

        // Not responsive → always use desktop
        if (!raw?.use_responsive) {
            return raw.desktop ?? ''
        }

        // Responsive → screenType overrides desktop
        const screen = props.screenType
        return raw?.[screen] ?? raw?.desktop ?? ''
    },

    set(newVal) {
        const copied = cloneDeep(props.modelValue)

        // Non-object value → direct replace
        if (!isPlainObject(copied)) {
            emits('update:modelValue', newVal)
            return
        }

        if (!copied.use_responsive) {
            // Non-responsive → always save to desktop
            copied.desktop = newVal
        } else {
            // Responsive → save by screen type
            const screen = props.screenType
            copied[screen] = newVal
        }

        emits('update:modelValue', copied)
    }
})


watch(
    () => props.modelValue,
    () => {
        key.value++
    }
)

watch(
    () => props.screenType,
    () => {
        key.value++
    }
)
</script>

<template>
    <EditorV2
        :key="key"
        v-model="valueForField"
        :uploadImageRoute="{
            name: webpageData.images_upload_route.name,
            parameters: { modelHasWebBlocks: blockData.id }
        }"
    />
</template>
