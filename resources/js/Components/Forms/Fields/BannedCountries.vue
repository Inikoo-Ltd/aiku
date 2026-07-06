<script setup lang="ts">
import { ref, reactive, computed, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import Multiselect from "@vueform/multiselect"
import InputText from "primevue/inputtext"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faTrashAlt, faLock, faVial, faCheck, faTimes, faSpinner } from "@fal"
import { get, debounce } from "lodash-es"
import { Select } from "primevue"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import Modal from "@/Components/Utils/Modal.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

interface BannedCountryRow {
    country: string | null
    postcode: string | null
    billing: boolean
    delivery: boolean
    ip_block: boolean
    read_only: boolean
}

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    options: Record<string, { label: string; code: string; id: number | string }>
    fieldData?: {
        placeholder?: string
        information?: string
        required?: boolean
        hideFollowOrganisation?: boolean
    }
}>()

// The country select uses the country code as value to stay consistent with the
// stored shape (form[fieldName] is keyed by country code).
const countryOptions = computed(() =>
    Object.values(props.options ?? {}).map((option) => ({
        label: option.label,
        value: option.code,
    }))
)

// Exclude countries already chosen in other rows so each country can be picked once.
const getCountryOptions = (currentRow: BannedCountryRow) => {
    const usedCodes = rows
        .filter((row) => row !== currentRow && row.country)
        .map((row) => row.country)

    return countryOptions.value.filter((option) => !usedCodes.includes(option.value))
}

const buildRows = (): BannedCountryRow[] => {
    const stored = props.form[props.fieldName]?.banned_list

    if (!stored || Array.isArray(stored)) {
        return []
    }

    return Object.entries(stored).map(([country, value]: [string, any]) => ({
        country,
        postcode: value?.postcode ?? "",
        billing: !!value?.billing,
        delivery: !!value?.delivery,
        ip_block: !!value?.ip_block,
        read_only: !!value?.read_only,
    }))
}

const rows = reactive<BannedCountryRow[]>(buildRows())

const isFollowOrganisationBannedList = ref(
    !!props.form[props.fieldName]?.is_follow_organisation_banned_list
)

const isDisabled = computed(() => isFollowOrganisationBannedList.value)

const syncForm = () => {
    const bannedList: Record<string, any> = {}

    rows.forEach((row) => {
        if (!row.country) {
            return
        }

        bannedList[row.country] = {
            postcode: row.postcode || null,
            billing: row.billing,
            delivery: row.delivery,
            ip_block: row.ip_block,
            ...(row.read_only ? { read_only: true } : {}),
        }
    })

    props.form[props.fieldName] = {
        banned_list: bannedList,
        is_follow_organisation_banned_list: isFollowOrganisationBannedList.value,
    }
}

watch(rows, syncForm, { deep: true })
watch(isFollowOrganisationBannedList, syncForm)

const addRow = () => {
    rows.push({
        country: null,
        postcode: "",
        billing: false,
        delivery: true,
        read_only: false,
        ip_block: false,
    })
}

const removeRow = (index: number) => {
    rows.splice(index, 1)
}

// At least one of billing/delivery must stay true. Reverting the change the user
// just made is the least surprising behaviour.
const onFlagChange = (row: BannedCountryRow, flag: "billing" | "delivery" | "ip_block") => {
    if (!row.billing && !row.delivery && !row.ip_block) {
        row[flag] = true
    }
}

// Section: Regex tester
// Mirrors the backend's preg_match($regex, $postcode): the stored value is a full
// PHP delimited pattern such as /^2/i, so we parse out body and flags before
// building a JS RegExp. Only flags JS understands are kept.
const isRegexTestOpen = ref(false)
const regexTestRow = ref<BannedCountryRow | null>(null)
const regexTestInput = ref("")
const isRegexTesting = ref(false)
const regexTestResult = ref<{ valid: boolean; matched: boolean } | null>(null)

const buildRegExp = (raw?: string | null): RegExp | null => {
    const pattern = (raw ?? "").trim()
    if (!pattern) {
        return null
    }

    const delimiter = pattern[0]
    const lastDelimiter = pattern.lastIndexOf(delimiter)

    const body = lastDelimiter > 0 ? pattern.slice(1, lastDelimiter) : pattern
    const rawFlags = lastDelimiter > 0 ? pattern.slice(lastDelimiter + 1) : ""
    const flags = rawFlags.split("").filter((flag) => "gimsuy".includes(flag)).join("")

    try {
        return new RegExp(body, flags)
    } catch {
        return null
    }
}

// Fake 0.5s "testing" delay on each keystroke so the check feels like it runs the regex.
const runRegexTest = debounce(() => {
    const regex = buildRegExp(regexTestRow.value?.postcode)

    regexTestResult.value = regex
        ? { valid: true, matched: regex.test(regexTestInput.value) }
        : { valid: false, matched: false }

    isRegexTesting.value = false
}, 500)

watch(regexTestInput, (value) => {
    regexTestResult.value = null

    if (!value) {
        isRegexTesting.value = false
        runRegexTest.cancel()
        return
    }

    isRegexTesting.value = true
    runRegexTest()
})

const openRegexTest = (row: BannedCountryRow) => {
    runRegexTest.cancel()
    regexTestRow.value = row
    regexTestInput.value = ""
    regexTestResult.value = null
    isRegexTesting.value = false
    isRegexTestOpen.value = true
}

const closeRegexTest = () => {
    runRegexTest.cancel()
    isRegexTestOpen.value = false
    regexTestRow.value = null
}
</script>

<template>
    <div>
        <label
            v-if="!fieldData?.hideFollowOrganisation"
            class="flex items-center gap-2 mb-4 cursor-pointer select-none w-fit"
            :class="{ 'opacity-100': true }"
        >
            <input
                v-model="isFollowOrganisationBannedList"
                type="checkbox"
                class="h-5 w-5 rounded cursor-pointer border-gray-300 hover:border-[--theme-color-0] text-[--theme-color-0] focus:ring-[--theme-color-0]"
            />
            <span class="text-sm font-medium text-gray-700">
                {{ ctrans("Follow Banned Countries from Organisation") }}
            </span>
        </label>

        <div :class="{ 'opacity-50 pointer-events-none': isDisabled }">
            <DataTable :value="rows" dataKey="country" class="text-sm" removableSort >
                <Column field="country" :header="ctrans('Country')" style="min-width: 16rem" sortable>
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon
                                v-if="data.read_only"
                                v-tooltip="trans('Inherited from Organisation')"
                                :icon="faLock"
                                class="text-gray-400"
                                fixed-width
                                aria-hidden="true"
                            />
                            <!-- <Select
                                v-model="data.country"
                                :options="countryOptions"
                                :filter="true"
                                :disabled="data.read_only || isDisabled"
                                :placeholder="fieldData?.placeholder ?? trans('Select country')"
                                class="w-full"
                            /> -->
                            <Select v-model="data.country"
                                filter
                                :options="getCountryOptions(data)"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Select a country"
                                :disabled="data.read_only || isDisabled"
                                filterPlaceholder="Type to search country"
                                class="w-full md:w-56"
                            />
                        </div>
                    </template>
                </Column>

                <Column xheader="ctrans('Postcode (regex)')" style="min-width: 12rem">
                    <template #header="{ column }">
                        <div class="font-semibold">{{ctrans('Banned Postcode (regex)')}} <VTooltip class="w-fit inline">
                                <span class='opacity-50 hover:opacity-100'>
                                    <FontAwesomeIcon icon='fal fa-info-circle' xsize="xs" fixed-width aria-hidden='true' />
                                </span>
                                <template #popper>
                                    <div class="min-w-20 w-fit max-w-96 text-sm">
                                        <ul class="list-disc list-outside pl-4">
                                            <li>
                                                {{ ctrans('You can use regex (regular expression) to match postcodes. For example, /^2/ will match any postcode starting with 2.') }}
                                            </li>
                                            <li>
                                                {{ ctrans('If you want to match any postcode, leave this field empty.') }}
                                            </li>
                                        </ul>
                                    </div>
                                </template>
                            </VTooltip></div>
                    </template>
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <InputText
                                v-model="data.postcode"
                                :disabled="data.read_only || isDisabled"
                                placeholder=""
                                class="w-full font-mono"
                                :invalid="get(form, ['errors', `${fieldName}.banned_list.${data.country}.postcode`])"
                            />
                            <button
                                v-tooltip="ctrans('Test the regex against a postcode')"
                                type="button"
                                :disabled="!data.postcode || isDisabled"
                                class="flex-none p-2 text-[--theme-color-0] hover:bg-gray-50 rounded-md transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                @click="openRegexTest(data)"
                            >
                                <FontAwesomeIcon :icon="faVial" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                        <span
                            v-if="get(form, ['errors', `${fieldName}.banned_list.${data.country}.postcode`])"
                            class="text-xs text-red-500"
                        >
                            {{ get(form, ['errors', `${fieldName}.banned_list.${data.country}.postcode`]) }}
                        </span>
                    </template>
                </Column>

                <Column :header="ctrans('Scoped')" style="min-width: 12rem">
                    <template #body="{ data }">
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="data.billing"
                                    type="checkbox"
                                    :disabled="data.read_only || isDisabled"
                                    class="h-5 w-5 rounded cursor-pointer border-gray-300 hover:border-[--theme-color-0] text-[--theme-color-0] focus:ring-[--theme-color-0]"
                                    @change="onFlagChange(data, 'billing')"
                                />
                                <span class="whitespace-nowrap">{{ ctrans("Billing") }} <InformationIcon :information="ctrans('If checked, billing address that matched the postcode will be banned')" /></span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="data.delivery"
                                    type="checkbox"
                                    :disabled="data.read_only || isDisabled"
                                    class="h-5 w-5 rounded cursor-pointer border-gray-300 hover:border-[--theme-color-0] text-[--theme-color-0] focus:ring-[--theme-color-0]"
                                    @change="onFlagChange(data, 'delivery')"
                                />
                                <span class="whitespace-nowrap">{{ ctrans("Delivery") }} <InformationIcon :information="ctrans('If active, delivery address that matched the postcode will be banned')" /></span>
                            </label>

                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="data.ip_block"
                                    type="checkbox"
                                    :disabled="data.read_only || isDisabled"
                                    class="h-5 w-5 rounded cursor-pointer border-gray-300 hover:border-[--theme-color-0] text-[--theme-color-0] focus:ring-[--theme-color-0]"
                                    @change="onFlagChange(data, 'ip_block')"
                                />
                                <span class="whitespace-nowrap">{{ ctrans("IP Block") }} <InformationIcon :information="ctrans('If active, IP location that matched the postcode will be banned')" /></span>
                            </label>
                        </div>
                    </template>
                </Column>

                <Column style="width: 4rem">
                    <template #body="{ data, index }">
                        <button
                            v-if="!data.read_only"
                            type="button"
                            :disabled="isDisabled"
                            class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors"
                            @click="removeRow(index)"
                        >
                            <FontAwesomeIcon :icon="faTrashAlt" fixed-width aria-hidden="true" />
                        </button>
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center text-gray-400 py-4">
                        {{ trans("No banned countries yet") }}
                    </div>
                </template>
            </DataTable>

            <button
                type="button"
                :disabled="isDisabled"
                class="mt-3 inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-[--theme-color-0] border border-dashed border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                @click="addRow"
            >
                <FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" />
                {{ trans("Add country") }}
            </button>
        </div>

        <p
            v-if="get(form, ['errors', fieldName])"
            class="mt-2 text-sm text-red-600"
            :id="`${fieldName}-error`"
        >
            {{ form.errors[fieldName] }}
        </p>

        <Modal :isOpen="isRegexTestOpen" @onClose="closeRegexTest" width="w-full max-w-md">
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ ctrans("Test postcode regex") }}
                </h3>

                <div class="text-sm text-gray-500">
                    {{ ctrans("Regex") }}:
                    <span class="font-mono text-gray-700">{{ regexTestRow?.postcode }}</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ ctrans("Postcode to test") }}
                    </label>
                    <InputText
                        v-model="regexTestInput"
                        autofocus
                        :placeholder="ctrans('Type a postcode')"
                        class="w-full font-mono"
                    />
                </div>

                <div
                    v-if="isRegexTesting"
                    class="flex items-center gap-2 rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-sm text-gray-500"
                >
                    <LoadingIcon />
                    {{ ctrans("Testing...") }}
                </div>

                <div
                    v-else-if="regexTestResult && !regexTestResult.valid"
                    class="rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-700"
                >
                    {{ ctrans("This is not a valid regex.") }}
                </div>

                <div
                    v-else-if="regexTestResult && regexTestResult.matched"
                    class="rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-700"
                >
                    <div class="flex items-center gap-2">
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                        {{ ctrans("This postcode matches the regex and will be banned.") }}
                    </div>
                    <div class="mt-1 pl-6 text-green-600">
                        {{ ctrans("All users from this postcode will be banned.") }}
                    </div>
                </div>

                <div
                    v-else-if="regexTestResult"
                    class="flex items-center gap-2 rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-sm text-gray-500"
                >
                    <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                    {{ ctrans("This postcode does not match the regex and will not be banned.") }}
                </div>

                <div class="flex justify-end">
                    <button
                        type="button"
                        class="px-3 py-2 text-sm font-medium text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                        @click="closeRegexTest"
                    >
                        {{ ctrans("Close") }}
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<style src="@vueform/multiselect/themes/default.css"></style>
