<script setup lang="ts">
import { computed, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck } from "@fas"
import { faBarcode } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
import axios from "axios"
library.add(faCheck, faBarcode)

const props = defineProps<{
    form: any
    fieldName: string
    options: {
        options: { value: string; code: string; name: string; proposed: boolean }[]
        withoutBarcode?: { code: string; name: string }[]
        hasChoice: boolean
        nextFreeRoute?: { name: string; parameters?: Record<string, unknown> }
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

const isGenerating = ref(false)

const generateFromPool = async () => {
    if (!props.options.nextFreeRoute) {
        return
    }
    isGenerating.value = true
    try {
        const response = await axios.get(route(props.options.nextFreeRoute.name, props.options.nextFreeRoute.parameters ?? {}))
        if (response.data.number) {
            pick(response.data.number)
        } else {
            props.form.errors[props.fieldName] = ctrans("The barcode pool has no free barcodes left")
        }
    } catch {
        props.form.errors[props.fieldName] = ctrans("Could not get a barcode from the pool")
    } finally {
        isGenerating.value = false
    }
}

const setOther = (value: string) => {
    otherBarcode.value = value
    props.form[props.fieldName] = value || null
    props.form.errors[props.fieldName] = null
}
</script>

<template>
    <div v-if="options.nextFreeRoute" class="space-y-2">
        <div class="flex items-center gap-x-3 px-2 py-1.5">
            <span v-if="current" class="font-mono text-sm text-gray-900">{{ current }}</span>
            <span v-else class="text-sm text-gray-500">{{ ctrans("No barcode") }}</span>
            <template v-if="!fieldData?.readonly">
                <Button
                    v-if="!current"
                    :label="ctrans('Generate barcode')"
                    icon="fal fa-barcode"
                    type="secondary"
                    size="xs"
                    :loading="isGenerating"
                    @click="generateFromPool" />
                <Button v-else :label="ctrans('Remove')" type="tertiary" size="xs" @click="pick(null)" />
            </template>
        </div>
        <p class="px-2 text-xs text-gray-500">
            {{ ctrans("Takes the next free barcode of the pool. Save to keep it; a removed barcode is not handed out again.") }}
        </p>
        <p v-if="form.errors[fieldName]" class="mt-2 text-sm text-red-600">{{ form.errors[fieldName] }}</p>
    </div>

    <div v-else class="space-y-1">
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

        <div
            v-for="tradeUnit in options.withoutBarcode ?? []"
            :key="tradeUnit.code"
            class="flex items-center gap-x-3 px-2 py-1.5 text-gray-400">
            <input type="radio" disabled />
            <span class="font-mono text-xs w-28 shrink-0">{{ tradeUnit.code }}</span>
            <span class="text-sm flex-1 truncate">{{ tradeUnit.name }}</span>
            <span class="text-xs whitespace-nowrap">{{ ctrans("No barcode on this trade unit") }}</span>
        </div>

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
