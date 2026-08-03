<script setup lang="ts">
import AutoComplete from "primevue/autocomplete"
import { computed, ref, watch } from "vue"

const props = defineProps<{
    form: any
    fieldName: string
    options: Array<string | { label?: string; value: string }>
    fieldData?: {
        placeholder?: string
        required?: boolean
        readonly?: boolean
        searchable?: boolean
    }
}>()

const query = ref("")
const suggestions = ref<string[]>([])

const normalizedOptions = computed((): string[] => {
    const values = (props.options ?? [])
        .map((option) => {
            if (typeof option === "string") {
                return option
            }

            return option.label ?? option.value
        })
        .map((value) => String(value ?? "").trim())
        .filter((value) => value.length > 0)

    return Array.from(new Set(values)).sort((a, b) => a.localeCompare(b))
})

watch(
    normalizedOptions,
    (value) => {
        suggestions.value = value.slice(0, 50)
    },
    { immediate: true }
)

const complete = (event: { query?: string }) => {
    const value = String(event?.query ?? "").trim().toLowerCase()
    query.value = value

    if (!value) {
        suggestions.value = normalizedOptions.value.slice(0, 50)
        return
    }

    suggestions.value = normalizedOptions.value
        .filter((item) => item.toLowerCase().includes(value))
        .slice(0, 50)
}

watch(
    () => props.form?.[props.fieldName],
    () => {
        if (props.form?.errors?.[props.fieldName]) {
            props.form.errors[props.fieldName] = ""
        }
    }
)
</script>

<template>
    <div>
        <AutoComplete
            v-model="form[fieldName]"
            :suggestions="suggestions"
            dropdown
            :forceSelection="false"
            :disabled="fieldData?.readonly"
            :placeholder="fieldData?.placeholder ?? 'Job Title'"
            class="w-full"
            inputClass="w-full"
            @complete="complete"
        />

        <p v-if="form?.errors?.[fieldName]" class="mt-2 text-sm text-red-600">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
