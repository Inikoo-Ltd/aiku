<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { Checkbox, InputNumber, InputText } from 'primevue'
import { computed } from 'vue'
import { ref } from 'vue'

const props = defineProps<{
    routeDownload: routeType | null
    listColumn?: {
        label: string
        is_checked: boolean
        value: string
    }[]
}>()

const exportDetails = defineModel<{ incoterms: string | null, net_weight: number | null }>('exportDetails', { default: () => ({ incoterms: null, net_weight: null }) })

const inEnglish = ref(false)

const selectedCheck = ref<string[]>((props.listColumn || []).map(check => check.is_checked ? check.value : null).filter(Boolean) as string[])

const compSelectedDeck = computed(() => {
    const columnFlags = Object.fromEntries(
        (props.listColumn || []).map(check => [check.value, selectedCheck.value.includes(check.value) ? 1 : 0])
    )

    if (!props.routeDownload) return '#'

    const exportParams = { incoterms: exportDetails.value.incoterms?.trim() ?? '', net_weight: exportDetails.value.net_weight ?? '' }

    return route(props.routeDownload?.name, { ...props.routeDownload?.parameters, ...columnFlags, ...exportParams, ...(inEnglish.value ? { lang: 'en' } : {}) })
})
</script>

<template>
    <div class="isolate bg-white px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center mb-4">
            <h2 class="text-lg font-bold tracking-tight sm:text-2xl">
                {{ ctrans("Invoice") }}
            </h2>
        </div>

        <div class="flex flex-col gap-2">
            <div>{{ ctrans("Select additional information to included:") }}</div>
            <div v-for="check of (props.listColumn || [])" :key="check.value" class="flex items-center gap-2">
                <Checkbox v-model="selectedCheck" :inputId="check.value" :name="check.value" :value="check.value" />
                <label :for="check.value" class="cursor-pointer">{{ check.label }}</label>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox v-model="inEnglish" inputId="lang_en" name="lang_en" binary />
                <label for="lang_en" class="cursor-pointer">{{ ctrans("In English") }}</label>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1">
                <label for="incoterms" class="text-sm">{{ ctrans("Incoterms") }}</label>
                <InputText v-model="exportDetails.incoterms" id="incoterms" placeholder="DAP" maxlength="100" size="small" />
            </div>
            <div class="flex flex-col gap-1">
                <label for="net_weight" class="text-sm">{{ ctrans("Net weight") }}</label>
                <InputNumber v-model="exportDetails.net_weight" inputId="net_weight" suffix=" kg" :min="0" :maxFractionDigits="3" size="small" fluid />
            </div>
        </div>
        <div class="mt-1 text-xs text-gray-500">{{ ctrans("For export deliveries; kept for the next download of this invoice") }}</div>

        <a :href="compSelectedDeck" target="_blank" rel="noopener noreferrer"
            class="w-full block mt-6">
            <Button full :label="ctrans('Download Invoice')" />
        </a>
    </div>
</template>
