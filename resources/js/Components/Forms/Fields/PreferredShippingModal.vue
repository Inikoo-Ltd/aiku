<script setup lang="ts">
import { ref, computed } from "vue"
import { useForm, router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronRight, faTrashAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import { Select } from "primevue"

library.add(faChevronRight, faTrashAlt)

interface PreferredShippingRow {
    id: number
    shipper_id: number
    shipper_name: string | null
    country_id: number | null
    country_name: string | null
    postcode: string | null
}

const props = defineProps<{
    fieldName: string
    fieldData: {
        value: PreferredShippingRow[]
        options: {
            shippers: { id: number; name: string }[]
            countries: { value: number; label: string }[]
        }
        storeRoute: { name: string; parameters: Record<string, any> }
    }
}>()

const isOpen = ref(false)
const rows = ref<PreferredShippingRow[]>([...(props.fieldData.value ?? [])])

const shipperOptions = computed(() =>
    props.fieldData.options.shippers.map((shipper) => ({ label: shipper.name, value: shipper.id }))
)
const countryOptions = computed(() => props.fieldData.options.countries)

const newRow = useForm({
    shipper_id: null as number | null,
    country_id: null as number | null,
    postcode: "",
})

const isSubmitting = ref(false)

const syncRowsFromPage = (page: any) => {
    const section = (page.props.formData?.blueprint ?? []).find(
        (s: any) => s?.fields?.[props.fieldName]
    )
    if (section) {
        rows.value = [...(section.fields[props.fieldName].value ?? [])]
    }
}

const addRow = () => {
    newRow.post(route(props.fieldData.storeRoute.name, props.fieldData.storeRoute.parameters), {
        preserveScroll: true,
        onStart: () => (isSubmitting.value = true),
        onFinish: () => (isSubmitting.value = false),
        onSuccess: (page: any) => {
            syncRowsFromPage(page)
            newRow.reset()
        },
    })
}

const removeRow = (row: PreferredShippingRow) => {
    router.delete(route("grp.models.preferred_shipping.delete", { preferredShipping: row.id }), {
        preserveScroll: true,
        onSuccess: (page: any) => syncRowsFromPage(page),
    })
}
</script>

<template>
    <div>
        <div
            class="border rounded-lg p-4 flex justify-between items-center cursor-pointer hover:shadow-md transition border-gray-300"
            @click="isOpen = true"
        >
            <span class="text-gray-700">
                {{ rows.length ? trans(":count rule(s) configured", { count: rows.length }) : trans("Default (no rules configured)") }}
            </span>
            <FontAwesomeIcon icon="fal fa-chevron-right" class="text-gray-400" />
        </div>

        <Modal :isOpen="isOpen" @onClose="isOpen = false" width="w-full max-w-2xl">
            <div class="space-y-4">
                <h2 class="text-lg font-semibold">{{ trans("Preferred Shipping") }}</h2>

                <table class="w-full text-sm" v-if="rows.length">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-1">{{ trans("Shipper") }}</th>
                            <th class="py-1">{{ trans("Country") }}</th>
                            <th class="py-1">{{ trans("Postcode") }}</th>
                            <th class="py-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id" class="border-t">
                            <td class="py-2">{{ row.shipper_name }}</td>
                            <td class="py-2">{{ row.country_name ?? trans("Any") }}</td>
                            <td class="py-2">{{ row.postcode ?? "-" }}</td>
                            <td class="py-2 text-right">
                                <button @click="removeRow(row)" class="text-red-500 hover:text-red-700">
                                    <FontAwesomeIcon icon="fal fa-trash-alt" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-gray-500">{{ trans("No rules yet, orders will use the default shipper.") }}</p>

                <div class="flex items-end gap-2 pt-2 border-t">
                    <div class="flex-1">
                        <label class="text-xs text-gray-500">{{ trans("Shipper") }}</label>
                        <Select v-model="newRow.shipper_id" :options="shipperOptions" optionLabel="label" optionValue="value"
                            :placeholder="trans('Select shipper')" class="w-full" />
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-gray-500">{{ trans("Country") }}</label>
                        <Select v-model="newRow.country_id" :options="countryOptions" optionLabel="label" optionValue="value"
                            :placeholder="trans('Any')" showClear class="w-full" />
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-gray-500">{{ trans("Postcode") }}</label>
                        <PureInput v-model="newRow.postcode" :placeholder="trans('Any')" />
                    </div>
                    <Button :label="trans('Add')" :loading="isSubmitting" :disabled="!newRow.shipper_id" @click="addRow" />
                </div>

                <div class="flex justify-end pt-2">
                    <Button type="tertiary" :label="trans('Close')" @click="isOpen = false" />
                </div>
            </div>
        </Modal>
    </div>
</template>
