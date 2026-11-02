<!--
  - Author Louis Perez
  - Created on 02-11-2026-14h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxOpen, faFlask, faArrowLeft, faExclamationTriangle, faLock } from "@fal"
library.add(faBoxOpen, faFlask, faArrowLeft, faExclamationTriangle, faLock)

type Kind = "product" | "recipe"

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: {
        value: Kind
        options: { value: Kind, label: string, description: string, icon: string }[]
        blocked: string | null
        route: routeType
        changes: Record<Kind, string[]>
        productions: { value: number, label: string, organisation: string | null }[]
    }
}>()

const current = ref<Kind>(props.fieldData.value)
const target = ref<Kind | null>(null)
const productionId = ref<number | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const toggleKey = ref(0)

const toggleOptions = computed(() => props.fieldData.options.map((option) => ({
    value: option.value,
    label: option.label,
    icon: option.icon,
    disabled: !!props.fieldData.blocked && option.value !== current.value,
})))

const selected = computed(() => props.fieldData.options.find((option) => option.value === current.value))
const targetOption = computed(() => props.fieldData.options.find((option) => option.value === target.value))

const picked = computed({
    get: () => current.value,
    set: (value: Kind) => {
        if (value === current.value) {
            return
        }
        target.value = value
        productionId.value = null
        errors.value = {}
        toggleKey.value++
    },
})

const dialogVisible = computed({
    get: () => target.value !== null,
    set: (visible: boolean) => {
        if (!visible) {
            target.value = null
            toggleKey.value++
        }
    },
})

function confirmChange() {
    if (!target.value) {
        return
    }
    router.patch(route(props.fieldData.route.name, props.fieldData.route.parameters), {
        kind: target.value,
        production_id: target.value === "recipe" ? productionId.value : null,
    }, {
        preserveScroll: true,
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onError: (validationErrors) => errors.value = validationErrors,
        onSuccess: () => {
            current.value = target.value as Kind
            props.form[props.fieldName] = current.value
            target.value = null
            toggleKey.value++
        },
    })
}
</script>

<template>
    <div class="space-y-2">
        <SegmentedToggle :key="toggleKey" v-model="picked" :options="toggleOptions" :ariaLabel="ctrans('Development kind')" />
        <p v-if="selected" class="text-xs text-gray-500">{{ selected.description }}</p>
        <p v-if="fieldData.blocked" class="flex items-start gap-1.5 text-xs text-amber-700">
            <FontAwesomeIcon icon="fal fa-lock" class="mt-0.5" fixed-width aria-hidden="true" />
            {{ fieldData.blocked }}
        </p>

        <Dialog v-model:visible="dialogVisible" modal :header="targetOption ? ctrans('Change to :kind?', { kind: targetOption.label.toLowerCase() }) : ''" :style="{ width: '30rem' }" :breakpoints="{ '640px': '94vw' }">
            <div v-if="target" class="space-y-3 text-sm">
                <div class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
                    <ul class="list-disc space-y-1 pl-4">
                        <li v-for="change in fieldData.changes[target]" :key="change">{{ change }}</li>
                    </ul>
                </div>

                <div v-if="target === 'recipe'">
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Made by") }}</label>
                    <Select v-model="productionId" :options="fieldData.productions" optionLabel="label" optionValue="value" size="small" class="w-full" :placeholder="ctrans('Choose the production unit')" :invalid="!!errors.production_id">
                        <template #option="{ option }">
                            <div>
                                <div>{{ option.label }}</div>
                                <div v-if="option.organisation" class="text-xs text-gray-500">{{ option.organisation }}</div>
                            </div>
                        </template>
                    </Select>
                    <p v-if="errors.production_id" class="mt-1 text-xs text-red-600">{{ errors.production_id }}</p>
                </div>

                <p v-if="errors.kind" class="text-xs text-red-600">{{ errors.kind }}</p>
            </div>

            <template #footer>
                <Button type="tertiary" size="xs" :label="ctrans('Keep it')" @click="dialogVisible = false">
                    <template #icon><FontAwesomeIcon icon="fal fa-arrow-left" class="text-gray-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="targetOption ? ctrans('Change to :kind', { kind: targetOption.label.toLowerCase() }) : ''" :disabled="target === 'recipe' && !productionId" :loading="saving" @click="confirmChange">
                    <template #icon><FontAwesomeIcon :icon="targetOption?.icon ?? 'fal fa-flask'" class="text-amber-500" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>
    </div>
</template>
