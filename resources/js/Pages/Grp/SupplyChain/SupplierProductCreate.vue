<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, nextTick, reactive, ref, watch } from "vue"
import axios from "axios"
import { debounce } from "lodash-es"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import SupplierProductFormField from "@/Components/SupplyChain/SupplierProductFormField.vue"
import type { SupplierProductFinding as Finding } from "@/Components/SupplyChain/SupplierProductFormField.vue"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSpinnerThird } from "@fal"

library.add(faSpinnerThird)

interface Field {
    key: string
    label: string
    required: boolean
    placeholder: string | null
    value: string | number | null
}

interface Review {
    id: string
    status: "running" | "done"
    summary: string | null
    suggestion: string | null
    note: string | null
}

interface RouteDef {
    name: string
    parameters: Record<string, any>
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    supplier: { name: string; currency: string | null }
    sections: { title: string; fields: Field[] }[]
    routes: { check: RouteDef; store: RouteDef }
}>()

const fields = props.sections.flatMap((section) => section.fields)
const fieldsByKey = Object.fromEntries(fields.map((field) => [field.key, field]))

const form = reactive<Record<string, string>>(Object.fromEntries(fields.map((field) => [field.key, field.value === null ? "" : String(field.value)])))
const skoName = ref("")
const isSkoNameEdited = ref(false)
const values = ref<Record<string, any>>({})
const findings = ref<Finding[]>([])
const review = ref<Review | null>(null)
const acceptedMessages = reactive<Record<string, string>>({})
const touched = reactive<Record<string, boolean>>({})
const showAllErrors = ref(false)
const isChecking = ref(false)
const hasChecked = ref(false)
const isReviewing = ref(false)
const isSaving = ref(false)
const isModalOpen = ref(false)
const modalColumns = ref<string[]>([])
const saveErrors = ref<string[]>([])

const isAccepted = (finding: Finding) => acceptedMessages[finding.code] === finding.message
const toggleAccepted = (finding: Finding, accepted: boolean) => {
    if (accepted) {
        acceptedMessages[finding.code] = finding.message
    } else {
        delete acceptedMessages[finding.code]
    }
}

const payload = (extra: Record<string, any> = {}) => ({
    ...form,
    sko_name: needsSkoName.value && isSkoNameEdited.value ? skoName.value : null,
    review: review.value?.id ?? null,
    accepted: findings.value.filter((finding) => isAccepted(finding)).map((finding) => finding.code),
    ...extra,
})

let checkSequence = 0
const check = async (extra: Record<string, any> = {}) => {
    const sequence = ++checkSequence
    isChecking.value = true
    try {
        const { data } = await axios.post(route(props.routes.check.name, props.routes.check.parameters), payload(extra))
        if (sequence !== checkSequence) {
            return
        }
        findings.value = data.findings
        values.value = data.values
        review.value = data.review
        hasChecked.value = true
        if (!isSkoNameEdited.value) {
            skoName.value = data.values.sko_name ?? ""
        }
    } finally {
        if (sequence === checkSequence) {
            isChecking.value = false
        }
    }
}
const debouncedCheck = debounce(() => check(), 400)
watch(form, debouncedCheck, { deep: true })
watch(skoName, () => isSkoNameEdited.value && debouncedCheck())

const isVisible = (finding: Finding) =>
    !finding.code.startsWith("required_") || showAllErrors.value || (finding.column !== null && touched[finding.column])
const fieldFindings = (key: string) => findings.value.filter((finding) => finding.column === key && isVisible(finding))
const otherFindings = computed(() => findings.value.filter((finding) => (finding.column === null || !fieldsByKey[finding.column]) && isVisible(finding)))

const errorCount = computed(() => findings.value.filter((finding) => finding.level === "error").length)
const decisionCount = computed(() => findings.value.filter((finding) => ["block", "link"].includes(finding.level) && !isAccepted(finding)).length)
const needsSkoName = computed(() => Number(values.value.units_per_sko ?? 1) > 1)
watch(needsSkoName, (needed) => !needed && (isSkoNameEdited.value = false))
const isReady = computed(() => hasChecked.value && !isChecking.value && errorCount.value === 0 && decisionCount.value === 0 && skoName.value.trim() !== "")

const waitForReview = async () => {
    const giveUpAt = Date.now() + 5 * 60 * 1000
    while (review.value?.status === "running" && Date.now() < giveUpAt) {
        await new Promise((resolve) => setTimeout(resolve, 2000))
        await check()
    }
}

const scrollToOpenFinding = async () => {
    await nextTick()
    document.querySelector("[data-finding-open]")?.scrollIntoView({ behavior: "smooth", block: "center" })
}

const checkBeforeSaving = async () => {
    debouncedCheck.cancel()
    saveErrors.value = []
    isReviewing.value = true
    try {
        await check()
        if (errorCount.value > 0) {
            showAllErrors.value = true
            await scrollToOpenFinding()
            return
        }
        await check({ start_review: true })
        await waitForReview()
    } catch {
        saveErrors.value = [ctrans("The checks did not finish, press Save again.")]
        return
    } finally {
        isReviewing.value = false
    }

    if (review.value?.status !== "done") {
        saveErrors.value = [ctrans("The checks did not finish, press Save again.")]
        return
    }

    showAllErrors.value = true
    const hasSomethingToShow = findings.value.length > 0 || review.value.suggestion || review.value.summary || review.value.note
    if (!hasSomethingToShow && isReady.value) {
        submit()
        return
    }

    modalColumns.value = [...new Set(findings.value.map((finding) => finding.column).filter((column): column is string => !!column && !!fieldsByKey[column]))]
    isModalOpen.value = true
}

const submit = () => {
    router.post(route(props.routes.store.name, props.routes.store.parameters), payload({ sko_name: needsSkoName.value ? skoName.value : null }), {
        onStart: () => (isSaving.value = true),
        onFinish: () => (isSaving.value = false),
        onError: async (errors) => {
            saveErrors.value = Object.values(errors).flat() as string[]
            if (errors.review) {
                review.value = null
                isModalOpen.value = false
            }
            await scrollToOpenFinding()
        },
    })
}
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                type="save"
                :label="isReviewing ? ctrans('Checking…') : ctrans('Save')"
                :loading="isReviewing || isSaving"
                :disabled="isReviewing || isSaving"
                @click="checkBeforeSaving"
            />
        </template>
    </PageHeading>

    <div class="p-4 space-y-4 text-sm text-gray-700">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-gray-500">
            <span>{{ ctrans("Supplier") }} <span class="font-medium text-gray-700">{{ supplier.name }}</span></span>
            <span>{{ ctrans("Creates the trade unit, its barcode, the SKO and this supplier product, with their families if they are new.") }}</span>
            <span class="flex flex-wrap items-center gap-1.5 text-xs tabular-nums">
                <FontAwesomeIcon v-if="isChecking" icon="fal fa-spinner-third" spin fixed-width />
                <span v-if="errorCount" class="rounded-full bg-red-50 px-2 py-0.5 text-red-700 ring-1 ring-inset ring-red-200">{{ errorCount }} {{ ctrans("to fix") }}</span>
                <span v-if="decisionCount" class="rounded-full bg-orange-50 px-2 py-0.5 text-orange-700 ring-1 ring-inset ring-orange-200">{{ decisionCount }} {{ ctrans("need a decision") }}</span>
            </span>
        </div>

        <div v-if="isReviewing" role="status" class="flex items-center gap-2 rounded border border-orange-200 bg-orange-50 px-3 py-2 text-xs text-orange-800">
            <FontAwesomeIcon icon="fal fa-spinner-third" spin fixed-width />
            {{ ctrans("Checking the product, including the AI checks. This can take a minute.") }}
        </div>

        <div v-if="saveErrors.length && !isModalOpen" class="rounded border border-red-200 bg-red-50 p-3 text-red-700">
            <div class="font-semibold">{{ ctrans("Not created") }}</div>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="error in saveErrors" :key="error">{{ error }}</li>
            </ul>
        </div>

        <SupplierProductFormField v-if="otherFindings.length" id="other-findings" :findings="otherFindings" :isAccepted="isAccepted" class="rounded border border-gray-200 p-3" @accept="toggleAccepted" />

        <section v-for="section in sections" :key="section.title" class="rounded border border-gray-200 p-3">
            <h3 class="font-semibold">{{ section.title }}</h3>
            <div class="mt-2 grid grid-cols-1 gap-x-4 gap-y-3 md:grid-cols-2 xl:grid-cols-3">
                <SupplierProductFormField
                    v-for="field in section.fields"
                    :key="field.key"
                    v-model="form[field.key]"
                    :id="`field-${field.key}`"
                    :label="field.label"
                    :required="field.required"
                    :placeholder="field.placeholder"
                    :findings="fieldFindings(field.key)"
                    :isAccepted="isAccepted"
                    @accept="toggleAccepted"
                    @blur="touched[field.key] = true"
                />
            </div>
            <div v-if="section.fields.some((field) => field.key === 'units_per_sko') && needsSkoName" class="mt-3 max-w-xl">
                <SupplierProductFormField
                    v-model="skoName"
                    id="field-sko-name"
                    :label="ctrans('SKO name')"
                    required
                    :placeholder="ctrans('e.g. Pack of :units :name', { units: values.units_per_sko, name: values.unit_name ?? '' })"
                    :findings="[]"
                    :isAccepted="isAccepted"
                    @update:modelValue="isSkoNameEdited = true"
                />
            </div>
        </section>
    </div>

    <Modal :isOpen="isModalOpen" width="w-full max-w-3xl" closeButton :isClosableInBackground="false" @onClose="isModalOpen = false">
        <div class="space-y-4 text-sm text-gray-700">
            <div>
                <h2 class="text-lg font-semibold">{{ ctrans("Check before saving") }}</h2>
                <p class="text-gray-500">{{ ctrans("Fix what needs fixing here, tick what you accept, then press Submit to create the product.") }}</p>
            </div>

            <div v-if="review?.summary || review?.suggestion" class="rounded border border-gray-200 bg-gray-50 p-3">
                <div class="font-semibold">{{ ctrans("AI review") }}</div>
                <p v-if="review?.suggestion" class="mt-1"><span class="font-medium">{{ ctrans("AI suggests") }}:</span> {{ review.suggestion }}</p>
                <p v-if="review?.summary" class="mt-1 whitespace-pre-line text-gray-600">{{ review.summary }}</p>
            </div>
            <div v-else-if="review?.note" class="rounded border border-gray-200 bg-gray-50 p-3 text-gray-600">{{ review.note }}</div>

            <SupplierProductFormField v-if="otherFindings.length" id="modal-other-findings" :findings="otherFindings" :isAccepted="isAccepted" @accept="toggleAccepted" />

            <div class="grid grid-cols-1 gap-x-4 gap-y-3 md:grid-cols-2">
                <SupplierProductFormField
                    v-for="column in modalColumns"
                    :key="column"
                    v-model="form[column]"
                    :id="`modal-field-${column}`"
                    :label="fieldsByKey[column].label"
                    :required="fieldsByKey[column].required"
                    :placeholder="fieldsByKey[column].placeholder"
                    :findings="fieldFindings(column)"
                    :isAccepted="isAccepted"
                    @accept="toggleAccepted"
                />
                <SupplierProductFormField
                    v-if="needsSkoName"
                    v-model="skoName"
                    id="modal-field-sko-name"
                    :label="ctrans('SKO name')"
                    required
                    :findings="[]"
                    :isAccepted="isAccepted"
                    @update:modelValue="isSkoNameEdited = true"
                />
            </div>

            <ul v-if="saveErrors.length" class="list-disc rounded border border-red-200 bg-red-50 py-2 pl-8 pr-3 text-red-700">
                <li v-for="error in saveErrors" :key="error">{{ error }}</li>
            </ul>

            <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-3">
                <span v-if="!isReady && hasChecked && !isChecking" class="mr-auto text-xs text-orange-700">
                    {{ errorCount ? ctrans(":count to fix", { count: errorCount }) : "" }}
                    {{ decisionCount ? ctrans(":count need a decision", { count: decisionCount }) : "" }}
                </span>
                <Button type="tertiary" :label="ctrans('Back to the form')" @click="isModalOpen = false" />
                <Button type="save" :label="ctrans('Submit')" :loading="isSaving" :disabled="!isReady || isSaving" @click="submit" />
            </div>
        </div>
    </Modal>
</template>
