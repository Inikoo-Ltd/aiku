<script setup lang="ts">
import { computed, ref } from "vue"
import Select from "primevue/select"
import InputNumber, { type InputNumberInputEvent } from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowUp, faPlus, faTimes } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"

interface Entry {
    organisation_id: number
    safety_margin: number | null
}

interface Option {
    organisation_id: number
    name: string
    enabled: boolean
}

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: { options: Option[], readonly?: boolean }
    options?: Option[]
}>()

const entries = computed<Entry[]>(() => props.form[props.fieldName] ?? [])
const optionOf = (organisationId: number) => props.fieldData.options.find((option) => option.organisation_id === organisationId)
const addable = computed(() => props.fieldData.options.filter((option) => option.enabled && !entries.value.some((entry) => entry.organisation_id === option.organisation_id)))
const picked = ref<number | null>(null)

const setEntries = (next: Entry[]) => {
    props.form[props.fieldName] = next
}
const clearRowErrors = () => {
    const rowKeys = Object.keys(props.form.errors ?? {}).filter((key) => key.startsWith(`${props.fieldName}.`))
    if (rowKeys.length) {
        props.form.clearErrors(...rowKeys)
    }
}
const add = () => {
    if (picked.value === null) {
        return
    }
    setEntries([...entries.value, { organisation_id: picked.value, safety_margin: null }])
    picked.value = null
}
const remove = (index: number) => {
    clearRowErrors()
    setEntries(entries.value.filter((_, position) => position !== index))
}
const moveUp = (index: number) => {
    clearRowErrors()
    const next = [...entries.value]
    ;[next[index - 1], next[index]] = [next[index], next[index - 1]]
    setEntries(next)
}
const setMargin = (index: number, margin: number | null) => setEntries(entries.value.map((entry, position) => (position === index ? { ...entry, safety_margin: margin } : entry)))
const rowError = (index: number) => props.form.errors?.[`${props.fieldName}.${index}.organisation_id`] ?? props.form.errors?.[`${props.fieldName}.${index}.safety_margin`]
</script>

<template>
    <div class="w-full space-y-2 text-sm">
        <table v-if="entries.length" class="w-full">
            <thead class="whitespace-nowrap text-left text-xs text-gray-500">
                <tr>
                    <th class="py-1 pr-2 font-medium">{{ ctrans("Warehouse") }}</th>
                    <th class="py-1 pr-2 font-medium">{{ ctrans("Safety margin (SKOs)") }} <span class="text-red-500">*</span></th>
                    <th v-if="!fieldData.readonly" />
                </tr>
            </thead>
            <tbody>
                <template v-for="(entry, index) in entries" :key="entry.organisation_id">
                    <tr class="border-t border-gray-100">
                        <td class="py-1 pr-2">
                            <span class="text-gray-400">{{ index + 1 }}.</span>
                            {{ optionOf(entry.organisation_id)?.name ?? entry.organisation_id }}
                            <span v-if="!optionOf(entry.organisation_id)?.enabled" class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">{{ ctrans("Switched off in cross dispatch") }}</span>
                        </td>
                        <td class="py-1 pr-2">
                            <InputNumber
                                :modelValue="entry.safety_margin"
                                @update:modelValue="(value: number | null) => setMargin(index, value)"
                                @input="(event: InputNumberInputEvent) => setMargin(index, typeof event.value === 'number' ? event.value : null)"
                                :min="0"
                                :disabled="fieldData.readonly"
                                :useGrouping="false"
                                size="small"
                                inputClass="!w-20"
                            />
                        </td>
                        <td v-if="!fieldData.readonly" class="whitespace-nowrap py-1 text-right">
                            <Button v-if="index > 0" type="tertiary" size="xs" :tooltip="ctrans('Move up')" @click="moveUp(index)">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faArrowUp" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button type="tertiary" size="xs" class="ml-1" :tooltip="ctrans('Remove')" @click="remove(index)">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="rowError(index)">
                        <td colspan="3" class="pb-1 text-xs text-red-600">{{ rowError(index) }}</td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div v-if="addable.length && !fieldData.readonly" class="flex items-center gap-2">
            <Select v-model="picked" :options="addable" optionLabel="name" optionValue="organisation_id" :placeholder="ctrans('Add a warehouse')" size="small" class="min-w-56" />
            <Button type="tertiary" size="xs" :label="ctrans('Add')" :disabled="picked === null" @click="add">
                <template #icon>
                    <FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>
        <p v-else-if="!entries.length && !fieldData.readonly" class="text-xs text-gray-500">{{ ctrans("No other company ships for this company yet: both sides have to switch it on in Cross dispatch.") }}</p>

        <p v-if="form.errors?.[fieldName]" class="text-xs text-red-600">{{ form.errors[fieldName] }}</p>
    </div>
</template>
