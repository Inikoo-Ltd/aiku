<script setup lang="ts">
import { computed, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ctrans } from "@/Composables/useTrans"
library.add(faCheck)

const props = defineProps<{
    form: any
    fieldName: string
    options: {
        options: { value: string; code: string; name: string; proposed: boolean }[]
        hasChoice: boolean
    }
    fieldData?: {
        readonly?: boolean
    }
}>()

const memberValues = computed(() => props.options?.options?.map((option) => option.value) ?? [])

const current = computed(() => props.form[props.fieldName] ?? null)

const isOther = ref(!!current.value && !memberValues.value.includes(current.value))

const otherBarcode = ref(isOther.value ? current.value : "")

const pick = (value: string | null) => {
    isOther.value = false
    props.form[props.fieldName] = value
    props.form.errors[props.fieldName] = null
}

const pickOther = () => {
    isOther.value = true
    props.form[props.fieldName] = otherBarcode.value || null
    props.form.errors[props.fieldName] = null
}

const setOther = (value: string) => {
    otherBarcode.value = value
    props.form[props.fieldName] = value || null
    props.form.errors[props.fieldName] = null
}
</script>

<template>
    <div class="space-y-1">
        <label
            v-for="option in options.options"
            :key="option.value"
            class="flex items-center gap-x-3 px-2 py-1.5 rounded cursor-pointer hover:bg-gray-50"
            :class="!isOther && current === option.value ? 'bg-indigo-50' : ''">
            <input
                type="radio"
                :checked="!isOther && current === option.value"
                :disabled="fieldData?.readonly"
                @change="pick(option.value)" />
            <span class="font-mono text-xs text-gray-500 w-28 shrink-0">{{ option.code }}</span>
            <span class="text-sm text-gray-700 flex-1 truncate">{{ option.name }}</span>
            <span class="font-mono text-sm text-gray-900">{{ option.value }}</span>
            <span v-if="option.proposed" class="text-xs text-indigo-600 whitespace-nowrap">
                {{ ctrans("suggested") }}
            </span>
        </label>

        <label
            class="flex items-center gap-x-3 px-2 py-1.5 rounded cursor-pointer hover:bg-gray-50"
            :class="isOther ? 'bg-indigo-50' : ''">
            <input type="radio" :checked="isOther" :disabled="fieldData?.readonly" @change="pickOther()" />
            <span class="text-sm text-gray-700 w-28 shrink-0">{{ ctrans("Own GTIN") }}</span>
            <input
                class="font-mono text-sm border-gray-300 rounded py-1 flex-1"
                :value="otherBarcode"
                :disabled="fieldData?.readonly || !isOther"
                :placeholder="ctrans('A barcode bought for this bundle')"
                @input="setOther(($event.target as HTMLInputElement).value)" />
        </label>

        <label
            class="flex items-center gap-x-3 px-2 py-1.5 rounded cursor-pointer hover:bg-gray-50"
            :class="!isOther && !current ? 'bg-indigo-50' : ''">
            <input
                type="radio"
                :checked="!isOther && !current"
                :disabled="fieldData?.readonly"
                @change="pick(null)" />
            <span class="text-sm text-gray-700">{{ ctrans("No barcode") }}</span>
            <span class="text-xs text-gray-500">
                {{ ctrans("eBay refuses a listing with no GTIN in several categories") }}
            </span>
        </label>

        <p v-if="form.errors[fieldName]" class="mt-2 text-sm text-red-600">{{ form.errors[fieldName] }}</p>
    </div>
</template>
