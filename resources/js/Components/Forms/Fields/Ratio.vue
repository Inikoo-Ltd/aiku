<script setup lang="ts">
import { ref, computed, onMounted } from "vue"
import { trans } from "laravel-vue-i18n"
import { get } from 'lodash-es'

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    type?: string
}>()

const isSquareType = computed(() => props.form?.type === "square")

const value = computed<string | null>({
    get() {
        if (isSquareType.value) {
            if (props.form[props.fieldName] !== "1/1") {
                props.form[props.fieldName] = "1/1"
            }
            return "1/1"
        }

        return props.form?.[props.fieldName] ?? null
    },
    set(val) {
        props.form[props.fieldName] = isSquareType.value ? "1/1" : val
    }
})
const mode = ref<"4/1" | "1/1" | "custom">("4/1")

const customWidth = ref<number | null>(4)
const customHeight = ref<number | null>(1)

onMounted(() => {
    if (isSquareType.value) {
        mode.value = "1/1"
        props.form[props.fieldName] = "1/1"
        return
    }

    if (!value.value) return

    if (value.value === "4/1" || value.value === "1/1") {
        mode.value = value.value
    } else if (value.value.includes("/")) {
        mode.value = "custom"
        const [w, h] = value.value.split("/")
        customWidth.value = Number(w)
        customHeight.value = Number(h)
    }
})

const isSelected = (val: string) => {
    if (isSquareType.value) return val === "1/1"
    return mode.value === val
}

const selectRatio = (val: "4/1" | "1/1" | "custom") => {
    if (isSquareType.value) {
        mode.value = "1/1"
        value.value = "1/1"
        return
    }

    mode.value = val

    if (val === "custom") {
        customWidth.value = null
        customHeight.value = null
        return
    }

    value.value = val
}

const updateCustomRatio = () => {
    if (isSquareType.value) return
    if (mode.value !== "custom") return
    if (customWidth.value === null || customHeight.value === null) return
    value.value = `${customWidth.value}/${customHeight.value}`
}

const customRatio = computed(() => {
    if (customWidth.value === null || customHeight.value === null) return 1
    return customWidth.value / customHeight.value
})
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-4 grid-cols-1 md:grid-cols-2">

            <!-- 4/1 -->
            <div v-if="!isSquareType" @click="selectRatio('4/1')"
                class="border rounded-lg p-4 w-full flex flex-col min-h-[240px] transition cursor-pointer" :class="isSelected('4/1')
                    ? 'shadow-md scale-[1.02] border-blue-500'
                    : 'border-gray-300 hover:shadow-sm hover:scale-[1.01]'">

                <div class="bg-gray-200 rounded w-full" style="aspect-ratio: 4 / 1;"></div>

                <div class="mt-3 flex-1 text-left">
                    <p class="text-sm font-medium">4 / 1</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ trans('Very wide ratio, suitable for horizontal banners.') }}
                    </p>
                </div>
            </div>

            <!-- 1/1 -->
            <div @click="selectRatio('1/1')"
                class="border rounded-lg p-4 w-full flex flex-col min-h-[240px] transition cursor-pointer" :class="isSelected('1/1')
                    ? 'border-blue-600 ring-2 ring-blue-200'
                    : 'border-gray-300'">

                <div class="bg-gray-200 rounded w-full" style="aspect-ratio: 1 / 1;"></div>

                <div class="mt-3 flex-1 text-left">
                    <p class="text-sm font-medium">1 / 1</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ trans('Square ratio.') }}
                    </p>
                </div>
            </div>

            <!-- Custom -->
            <div v-if="!isSquareType" @click="selectRatio('custom')"
                class="border rounded-lg p-4 w-full flex flex-col min-h-[240px] transition cursor-pointer" :class="isSelected('custom')
                    ? 'border-blue-600 ring-2 ring-blue-200'
                    : 'border-gray-300'">

                <div class="bg-gray-200 rounded w-full" :style="{ aspectRatio: customRatio }">
                </div>

                <div class="mt-3 flex-1 text-left">
                    <p class="text-sm font-medium">
                        {{ trans('Custom') }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ trans('Define your own width and height ratio.') }}
                    </p>
                </div>
            </div>

        </div>

        <!-- Custom inputs -->
        <div v-if="mode === 'custom' && !isSquareType" class="flex items-center gap-2">

            <input type="number" v-model.number="customHeight" @input="updateCustomRatio" :placeholder="trans('Height')"
                class="border rounded px-2 py-1 w-24 text-sm" />


            <span>/</span>

            <input type="number" v-model.number="customWidth" @input="updateCustomRatio" :placeholder="trans('Width')"
                class="border rounded px-2 py-1 w-24 text-sm" />
        </div>

    </div>

    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>