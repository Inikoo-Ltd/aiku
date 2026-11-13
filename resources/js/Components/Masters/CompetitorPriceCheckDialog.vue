<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 06 Nov 2026 17:03:21 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import FileUpload from "primevue/fileupload"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faImage } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

const props = defineProps<{
    route: routeType
    competitorName: string
    currencyCode: string
    pricesByHand: boolean
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"

const blank = () => ({ master_asset_code: "", url: "", name: "", price: null as number | null, units: 1 as number | null, delivery_days: null as number | null, screenshot: null as File | null })
const form = ref(blank())
const errors = ref<Record<string, string>>({})
const isSaving = ref(false)

const preview = ref<string | null>(null)

function setScreenshot(file: File | null | undefined) {
    if (preview.value) {
        URL.revokeObjectURL(preview.value)
    }
    form.value.screenshot = file ?? null
    preview.value = file ? URL.createObjectURL(file) : null
}

function onPaste(event: ClipboardEvent) {
    const file = Array.from(event.clipboardData?.files ?? []).find((pasted) => pasted.type.startsWith("image/"))
    if (file) {
        event.preventDefault()
        setScreenshot(file)
    }
}

watch(visible, (isVisible) => {
    if (isVisible) {
        form.value = blank()
        errors.value = {}
        setScreenshot(null)
        window.addEventListener("paste", onPaste)
    } else {
        window.removeEventListener("paste", onPaste)
    }
})

onBeforeUnmount(() => {
    window.removeEventListener("paste", onPaste)
    setScreenshot(null)
})

const canSubmit = computed(() => form.value.master_asset_code && form.value.url && form.value.name && form.value.price !== null && form.value.screenshot && !isSaving.value)

function submit() {
    if (!canSubmit.value) {
        return
    }

    router.post(route(props.route.name, props.route.parameters), form.value, {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => {
            isSaving.value = true
            errors.value = {}
        },
        onFinish: () => (isSaving.value = false),
        onSuccess: () => (visible.value = false),
        onError: (fieldErrors: Record<string, string>) => (errors.value = fieldErrors ?? {}),
    })
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('Add a price seen on :name', { name: competitorName })" :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-3 text-sm">
            <div>
                <label for="price-check-code" class="mb-1 block text-xs text-gray-500">{{ ctrans("Our product code") }}</label>
                <InputText id="price-check-code" v-model="form.master_asset_code" placeholder="AWPS-01" :class="fieldFocusClass" fluid />
                <p v-if="errors.master_asset_code" class="mt-1 text-xs text-red-500">{{ errors.master_asset_code }}</p>
            </div>
            <div>
                <label for="price-check-url" class="mb-1 block text-xs text-gray-500">{{ ctrans("Link to their listing") }}</label>
                <InputText id="price-check-url" v-model="form.url" placeholder="https://" :class="fieldFocusClass" fluid />
                <p v-if="errors.url" class="mt-1 text-xs text-red-500">{{ errors.url }}</p>
            </div>
            <div>
                <label for="price-check-name" class="mb-1 block text-xs text-gray-500">{{ ctrans("Their product name") }}</label>
                <InputText id="price-check-name" v-model="form.name" :class="fieldFocusClass" fluid />
                <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label for="price-check-price" class="mb-1 block text-xs text-gray-500">{{ ctrans("Price for the pack") }}</label>
                    <InputNumber inputId="price-check-price" v-model="form.price" v-bind="locale.currencyInputOptions(currencyCode)" :min="0" :class="fieldFocusClass" fluid />
                    <p v-if="errors.price" class="mt-1 text-xs text-red-500">{{ errors.price }}</p>
                </div>
                <div>
                    <label for="price-check-units" class="mb-1 block text-xs text-gray-500">{{ ctrans("Units in the pack") }}</label>
                    <InputNumber inputId="price-check-units" v-model="form.units" :min="1" :maxFractionDigits="3" :class="fieldFocusClass" fluid />
                    <p v-if="errors.units" class="mt-1 text-xs text-red-500">{{ errors.units }}</p>
                </div>
                <div>
                    <label for="price-check-delivery" class="mb-1 block text-xs text-gray-500">{{ ctrans("Delivery days") }}</label>
                    <InputNumber inputId="price-check-delivery" v-model="form.delivery_days" :min="0" :max="365" :class="fieldFocusClass" fluid />
                    <p v-if="errors.delivery_days" class="mt-1 text-xs text-red-500">{{ errors.delivery_days }}</p>
                </div>
            </div>
            <div>
                <div class="mb-1 text-xs text-gray-500">{{ ctrans("Screenshot of their listing showing the price") }}</div>
                <div class="flex items-center gap-3">
                    <img v-if="preview" :src="preview" alt="" class="h-16 w-28 shrink-0 rounded border border-gray-200 object-cover object-top" />
                    <FileUpload mode="basic" accept="image/png,image/jpeg,image/webp" :maxFileSize="10485760" customUpload :auto="false" :chooseLabel="preview ? ctrans('Change') : ctrans('Choose file')" @select="(event) => setScreenshot(event.files?.[0])">
                        <template #chooseicon><FontAwesomeIcon :icon="faImage" fixed-width aria-hidden="true" /></template>
                    </FileUpload>
                    <span class="text-xs text-gray-500">{{ ctrans("or paste it here with Ctrl+V / Cmd+V") }}</span>
                </div>
                <p v-if="errors.screenshot" class="mt-1 text-xs text-red-500">{{ errors.screenshot }}</p>
            </div>
            <p v-if="pricesByHand" class="text-xs text-gray-500">{{ ctrans("Only add a listing that is the same item as ours: it counts in the price comparison. Use the price for buyers in the EU, VAT included, with delivery if they charge it. Checking the same link again next month adds a new row to the history.") }}</p>
            <p v-else class="text-xs text-gray-500">{{ ctrans("Only add a listing that is the same item as ours: it counts in the price comparison. Use the price their website shows for this size, in :currency, as their other prices are read. Add it for a size or product their website is not read for, or read wrong. Checking the same link again later adds a new row to the history.", { currency: currencyCode }) }}</p>
        </div>

        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="ctrans('Save price')" :loading="isSaving" :disabled="!canSubmit" @click="submit">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
