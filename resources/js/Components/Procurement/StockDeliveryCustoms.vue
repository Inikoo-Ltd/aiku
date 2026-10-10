<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 09 Oct 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useLocaleStore } from '@/Stores/locale'
import { ctrans } from '@/Composables/useTrans'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPassport, faPlus, faTrashAlt } from '@fal'

library.add(faPassport, faPlus, faTrashAlt)

type CustomsLine = {
    id?: number | null
    tariff_code: string
    description: string | null
    duty_rate: number | null
    customs_value: number | null
    duty_amount: number | null
    import_vat: number | null
    allocated?: number
    items_value?: number
}

const props = defineProps<{
    data: {
        customs_mrn: string | null
        customs_released_at: string | null
        org_currency: string
        duty_cost: number | null
        can_edit: boolean
        lines: CustomsLine[]
        items: any[]
        updateRoute: { name: string, parameters: Record<string, number> }
    }
    tab?: string
}>()

const locale = useLocaleStore()

const money = (value: number | null | undefined) => value === null || value === undefined ? '-' : locale.currencyFormat(props.data.org_currency, Number(value))

const toDate = (value: string | null) => value ? new Date(`${value}T00:00:00`) : null
const toIso = (date: Date | null) => date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` : null

const form = useForm<{
    customs_mrn: string | null
    customs_released_at: Date | null
    lines: CustomsLine[]
}>({
    customs_mrn: props.data.customs_mrn,
    customs_released_at: toDate(props.data.customs_released_at),
    lines: props.data.lines.map(line => ({ ...line })),
})

watch(() => props.data, (data) => {
    form.defaults({
        customs_mrn: data.customs_mrn,
        customs_released_at: toDate(data.customs_released_at),
        lines: data.lines.map(line => ({ ...line })),
    })
    form.reset()
})

function addLine() {
    form.lines.push({ id: null, tariff_code: '', description: null, duty_rate: 0, customs_value: 0, duty_amount: null, import_vat: null })
}

function lineDuty(line: CustomsLine) {
    return line.duty_amount ?? Math.round(Number(line.duty_rate) * Number(line.customs_value)) / 100
}

const declaredDuty = computed(() => form.lines.reduce((sum, line) => sum + lineDuty(line), 0))
const dutyDiffers = computed(() => props.data.duty_cost !== null && Math.abs(declaredDuty.value - Number(props.data.duty_cost)) > 0.01)

function save() {
    form
        .transform((data) => ({
            customs_mrn: data.customs_mrn,
            customs_released_at: toIso(data.customs_released_at),
            ...(props.data.can_edit ? {
                lines: data.lines.map(({ id, tariff_code, description, duty_rate, customs_value, duty_amount, import_vat }) => ({ id, tariff_code, description, duty_rate, customs_value, duty_amount, import_vat })),
            } : {}),
        }))
        .patch(route(props.data.updateRoute.name, props.data.updateRoute.parameters), {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: [props.tab ?? 'customs', 'box_stats', 'costing'] }),
        })
}

const lineOptions = computed(() => [
    { value: null, label: ctrans('No line (shares what the lines leave)') },
    ...props.data.lines.map(line => ({ value: line.id, label: `${line.tariff_code} · ${locale.number(line.duty_rate ?? 0)}%${line.description ? ` · ${line.description}` : ''}` })),
])

const savingItemId = ref<number | null>(null)

async function setItemLine(item: any, lineId: number | null) {
    savingItemId.value = item.id
    try {
        await axios.patch(route(item.updateRoute.name, item.updateRoute.parameters), { stock_delivery_customs_line_id: lineId })
        router.reload({ only: [props.tab ?? 'customs', 'box_stats', 'costing'] })
    } catch (error: any) {
        notify({ title: ctrans('Something went wrong'), text: error?.response?.data?.message ?? '', type: 'error' })
    } finally {
        savingItemId.value = null
    }
}
</script>

<template>
    <div class="space-y-6 p-4">
        <div class="flex flex-wrap items-end gap-4">
            <label class="space-y-1">
                <span class="block text-xs text-gray-500">{{ ctrans('MRN') }}</span>
                <InputText v-model="form.customs_mrn" class="w-64" />
            </label>
            <label class="space-y-1">
                <span class="block text-xs text-gray-500">{{ ctrans('Release date') }}</span>
                <DatePicker v-model="form.customs_released_at" dateFormat="yy-mm-dd" showIcon />
            </label>
            <p class="max-w-xl text-xs text-gray-500">
                {{ ctrans('Copy the tariff lines of the import declaration. Each item takes the duty of its own line, shared by value among the items on it; import VAT is recorded only, it is deducted in the VAT return and never costed.') }}
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs text-gray-500">
                    <tr>
                        <th class="py-2 pr-3 font-normal">{{ ctrans('Tariff code') }}</th>
                        <th class="py-2 pr-3 font-normal">{{ ctrans('Description') }}</th>
                        <th class="py-2 pr-3 text-right font-normal">{{ ctrans('Duty %') }}</th>
                        <th class="py-2 pr-3 text-right font-normal">{{ ctrans('Customs value') }}</th>
                        <th class="py-2 pr-3 text-right font-normal">{{ ctrans('Duty') }}</th>
                        <th class="py-2 pr-3 text-right font-normal">{{ ctrans('Import VAT') }}</th>
                        <th class="py-2 pr-3 text-right font-normal">{{ ctrans('Duty on items') }}</th>
                        <th class="py-2" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="(line, index) in form.lines" :key="line.id ?? `new-${index}`">
                        <td class="py-1.5 pr-3"><InputText v-model="line.tariff_code" :disabled="!data.can_edit" size="small" class="w-32" /></td>
                        <td class="py-1.5 pr-3"><InputText v-model="line.description" :disabled="!data.can_edit" size="small" class="w-48" /></td>
                        <td class="py-1.5 pr-3"><InputNumber v-model="line.duty_rate" :disabled="!data.can_edit" :maxFractionDigits="4" :min="0" :max="100" size="small" inputClass="w-20 text-right" /></td>
                        <td class="py-1.5 pr-3"><InputNumber v-model="line.customs_value" :disabled="!data.can_edit" :maxFractionDigits="2" :min="0" size="small" inputClass="w-28 text-right" /></td>
                        <td class="py-1.5 pr-3"><InputNumber v-model="line.duty_amount" :disabled="!data.can_edit" :placeholder="locale.number(lineDuty(line))" :maxFractionDigits="2" :min="0" size="small" inputClass="w-24 text-right" /></td>
                        <td class="py-1.5 pr-3"><InputNumber v-model="line.import_vat" :disabled="!data.can_edit" :maxFractionDigits="2" :min="0" size="small" inputClass="w-24 text-right" /></td>
                        <td class="py-1.5 pr-3 text-right tabular-nums text-gray-600">{{ line.id ? money(line.allocated) : '-' }}</td>
                        <td class="py-1.5">
                            <Button v-if="data.can_edit" icon="fal fa-trash-alt" type="tertiary" size="xs" :tooltip="ctrans('Remove line')" @click="form.lines.splice(index, 1)" />
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="text-sm">
                        <td colspan="4" class="py-2">
                            <Button v-if="data.can_edit" icon="fal fa-plus" :label="ctrans('Add line')" type="tertiary" size="xs" @click="addLine" />
                        </td>
                        <td class="py-2 pr-3 text-right font-semibold tabular-nums">{{ money(declaredDuty) }}</td>
                        <td colspan="3" />
                    </tr>
                </tfoot>
            </table>
        </div>

        <div v-if="dutyDiffers" class="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
            {{ ctrans('The lines declare :declared of duty but the duty cost of this delivery is :cost. The cost is what gets shared; items on no line take the difference.', { declared: money(declaredDuty), cost: money(data.duty_cost) }) }}
        </div>
        <div v-if="!data.can_edit" class="text-sm text-gray-500">{{ ctrans('The delivery is costed: reopen the costing to change the lines.') }}</div>
        <div v-for="(error, field) in form.errors" :key="field" class="text-sm text-red-600">{{ error }}</div>

        <div class="flex justify-end">
            <Button :label="ctrans('Save customs')" type="save" :loading="form.processing" :disabled="!form.isDirty" @click="save" />
        </div>

        <div v-if="data.lines.length">
            <h3 class="mb-2 text-sm font-medium text-gray-700">{{ ctrans('Items and their tariff line') }}</h3>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs text-gray-500">
                    <tr>
                        <th class="py-2 pr-3 font-normal">{{ ctrans('Code') }}</th>
                        <th class="py-2 pr-3 font-normal">{{ ctrans('Name') }}</th>
                        <th class="py-2 pr-3 font-normal">{{ ctrans('Our tariff code') }}</th>
                        <th class="py-2 pr-3 font-normal">{{ ctrans('Customs line') }}</th>
                        <th class="py-2 pr-3 text-right font-normal">{{ ctrans('Value') }}</th>
                        <th class="py-2 text-right font-normal">{{ ctrans('Duty') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in data.items" :key="item.id">
                        <td class="py-1.5 pr-3">{{ item.code }}</td>
                        <td class="py-1.5 pr-3 text-gray-600">{{ item.name }}</td>
                        <td class="py-1.5 pr-3 tabular-nums text-gray-600">{{ item.tariff_code ?? '-' }}</td>
                        <td class="py-1.5 pr-3">
                            <Select
                                :modelValue="item.stock_delivery_customs_line_id"
                                :options="lineOptions"
                                optionLabel="label"
                                optionValue="value"
                                :disabled="!data.can_edit"
                                :loading="savingItemId === item.id"
                                size="small"
                                class="w-72"
                                @update:modelValue="(value) => setItemLine(item, value)"
                            />
                        </td>
                        <td class="py-1.5 pr-3 text-right tabular-nums">{{ money(item.value) }}</td>
                        <td class="py-1.5 text-right tabular-nums">{{ money(item.cost_duties) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
