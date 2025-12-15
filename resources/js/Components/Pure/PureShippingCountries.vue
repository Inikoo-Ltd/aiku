<script setup lang="ts">
import { ref, computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from '../Elements/Buttons/Button.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInfinity, faPen, faPencil } from '@far'
import { faPlus } from '@fas'
import { faTrashAlt, faEdit } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PureMultiselect from './PureMultiselect.vue'
import PureTextarea from './PureTextarea.vue'
import ModalConfirmationDelete from '../Utils/ModalConfirmationDelete.vue'
import { trans } from 'laravel-vue-i18n'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import { get, set } from 'lodash'

library.add(faInfinity, faPlus, faTrashAlt, faPen)

const props = withDefaults(defineProps<{
    modelValue: Array<{
        country_code: string
        included_postal_codes?: string
        excluded_postal_codes?: string
    }>
    routes: {
        store: routeType
        update: routeType
        delete: routeType
    }
    country_list: {}[]
}>(), {
    // country_list: () => [],
})

console.log('country_list', props.country_list)

const emit = defineEmits(['update:modelValue'])

// const items = computed(() => props.modelValue || [])

const showModal = ref(false)
const selectedIndex = ref(-1)
const editCountryCode = ref('')
const editCountryId = ref(null)
const editIncludedPostalCodes = ref('')
const editExcludedPostalCodes = ref('')
const editTerritories = ref({})

const countryOptions = computed(() => {
    const list = props.country_list
    if (list && typeof list === 'object' && !Array.isArray(list)) {
        return Object.values(list).map((c: any) => ({
            id: c.id,
            label: c.label,
            code: (c.label.match(/\(([^)]+)\)/)?.[1] || '').toUpperCase(),
        }))
    }
    if (Array.isArray(list)) {
        return list.map((c: any) => ({
            id: c.id,
            label: c.label,
            code: (c.label.match(/\(([^)]+)\)/)?.[1] || '').toUpperCase(),
        }))
    }
    return []
})

function openEditModal(index: number) {
    const item = props.modelValue[index]
    selectedIndex.value = index
    editCountryCode.value = item.country_code
    // editIncludedPostalCodes.value = item.included_postal_codes || ''
    // editExcludedPostalCodes.value = item.excluded_postal_codes || ''
    showModal.value = true
}

function addNewItem() {
    selectedIndex.value = -1
    editCountryCode.value = ''
    // editIncludedPostalCodes.value = ''
    // editExcludedPostalCodes.value = ''
    showModal.value = true
}

function saveEdit() {
    const updated = [...props.modelValue]
    const newItem = {
        country_code: editCountryCode.value,
        included_postal_codes: editIncludedPostalCodes.value,
        excluded_postal_codes: editExcludedPostalCodes.value,
    }

    if (selectedIndex.value >= 0) {
        updated[selectedIndex.value] = newItem
    } else {
        updated.push(newItem)
    }

    emit('update:modelValue', updated)
    showModal.value = false
}

const isLoadingAddNewShippingCountry = ref(false)
const onAddNewShippingCountry = () => {
    // Section: Submit
    console.log('editCountryId.value', editCountryId.value, route(props.routes.store.name, props.routes.store.parameters))
    
    router.post(
        route(props.routes.store.name, props.routes.store.parameters),
        {
            country_id: editCountryId.value,
            territories: editTerritories.value
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingAddNewShippingCountry.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully submit the data"),
                    type: "success"
                })
                editTerritories.value = {}
                editCountryId.value = null
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set location"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingAddNewShippingCountry.value = false
            },
        }
    )
}

// function deleteItem(index: number) {
//     const updated = [...props.modelValue]
//     updated.splice(index, 1)
//     emit('update:modelValue', updated)
// }

function getCountryLabel(code: string): string {
    const found = countryOptions.value.find(c => c.code === code)
    return found ? found.label : code ?? ''
}

const aaa = [
    {
        country_code: 'US',
        included_postal_codes: ['10001', '10002', '10003'],
        excluded_postal_codes: ['10004', '10005'],
    },
    {
        country_code: 'CA',
        included_postal_codes: ['M5H', 'M5J', 'M5K'],
    },
]

const _modal_delete = ref<InstanceType<typeof ModalConfirmationDelete> | null>(null)
const selectedCountryToDelete = ref<any>(null)
</script>

<template>
    <div class="space-y-4 text-sm max-w-[450px] mx-auto">

        <!-- List of Regions -->
        <template v-if="modelValue?.length">
            <div v-for="(item, index) in modelValue" :key="index"
                class="p-3 rounded border border-gray-300 bg-white shadow-sm space-y-2">
                <!-- Country Code -->
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-gray-500">Country:</span>
                        <span class="font-mono font-medium">
                            <img class="inline pr-1 pl-1 h-[1em]"
                                :src="'/flags/' + item.country_code?.toLowerCase() + '.png'" :alt="item.country_code"
                                :title="getCountryLabel(item.country_code)" />
                            {{ getCountryLabel(item.country_code) }}
                        </span>
                    </div>
                    <!-- Section: Button (edit/delete) -->
                    <div class="flex items-center ">
                        <Button :icon="faPencil" type="edit" size="xs" @click="openEditModal(index)">
                            <template #icon>
                                <FontAwesomeIcon :icon="faPencil" class="" />
                            </template>
                        </Button>
                        
                        <Button :icon="faTrashAlt" type="edit" class="text-red-500" size="xs"
                            aclick="deleteItem(index)"
                            @click="() => (_modal_delete?.changeModel(), selectedCountryToDelete = item)"
                        >
                            <template #icon>
                                <FontAwesomeIcon :icon="faTrashAlt" class="text-red-500" />
                            </template>
                        </Button>
                    </div>
                </div>
                <!-- Section: Included Postal Codes -->
                <div v-if="item.included_postal_codes">
                    <div class="text-green-500 mb-1">Included Postal Codes</div>
                    <div class="bg-green-50 text-xs p-2 rounded whitespace-pre-wrap break-words">
                        {{ item.included_postal_codes.join(', ') }}
                    </div>
                </div>
                <!-- Section: Excluded Postal Codes -->
                <div v-if="item.excluded_postal_codes">
                    <div class="text-red-500 mb-1">Excluded Postal Codes</div>
                    <div class="bg-red-50 text-xs p-2 rounded whitespace-pre-wrap break-words">
                        {{ item.excluded_postal_codes.join(', ') }}
                    </div>
                </div>
            </div>
        </template>
        <p v-else class="text-center text-gray-500">No shipping countries added yet.</p>

        <div class="flex justify-end mb-2">
            <Button
                :icon="faPlus"
                label="Add shipping country"
                type="dashed"
                full
                @click="addNewItem"
            />
        </div>

        <pre>{{ props.modelValue }}</pre>
    </div>

    
    <!-- Section: Remove country -->
    <ModalConfirmationDelete
        ref="_modal_delete"
        :routeDelete="{
            name: props.routes.delete.name,
            parameters: {
                shippingCountry: selectedCountryToDelete?.id
            }
        }"
        @success="() => selectedCountryToDelete = null"
        :title="trans('Are you sure you want to remove :code?', { code: getCountryLabel(selectedCountryToDelete?.country_code) })"
        :description="trans('This will remove the country from allowed shipping countries list.')"
        isFullLoading
        :noLabel="trans('Yes, remove')"
        noIcon="fal fa-times"
    />

    <!-- Modal for Add/Edit -->
    <Dialog v-model:visible="showModal" modal header="Add Allowed Shipping Country" :style="{ width: '450px' }">
        <div class="space-y-4 text-sm">
            <!-- Add mode -->
            <div v-if="selectedIndex === -1">
                <label class="block mb-1 text-gray-600">Select country</label>
                <PureMultiselect
                    :modelValue="editCountryId"
                    @update:modelValue="val => editCountryId = val"
                    :options="countryOptions"
                    :searchable="true"
                    label="label"
                    valueProp="id"
                    mode="single"
                    required
                />
            </div>

            <!-- Edit mode -->
            <div v-else>
                <label class="block mb-1 text-gray-600">Country</label>
                <div class="flex items-center font-medium bg-gray-50 p-2 rounded">
                    <img class="inline pr-1 pl-1 h-[1em]" :src="'/flags/' + editCountryCode?.toLowerCase() + '.png'"
                        :alt="editCountryCode" :title="getCountryLabel(editCountryCode)" />
                    {{ getCountryLabel(editCountryCode) }}
                </div>
            </div>

            <div>
                <label class="block mb-1 text-gray-600">Included Postal Codes</label>
                <PureTextarea
                    :modelValue="get(editTerritories, ['included_postal_codes'], []).join(',')"
                    @update:modelValue="e => set(editTerritories, ['included_postal_codes'], e.split(',').map(s => s.trim()).filter(Boolean))"
                    rows="3"
                    class="w-full"
                    autoResize
                />
            </div>

            <div>
                <label class="block mb-1 text-gray-600">Excluded Postal Codes</label>
                <PureTextarea
                    :modelValue="get(editTerritories, ['excluded_postal_codes'], []).join(',')"
                    @update:modelValue="e => set(editTerritories, ['excluded_postal_codes'], e.split(',').map(s => s.trim()).filter(Boolean))"
                    rows="3"
                    class="w-full"
                    autoResize
                />
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" type="exit" @click="showModal = false" />
            <Button v-if="selectedIndex === -1" type="create" :label="trans('Add')" @click="onAddNewShippingCountry" full :loading="isLoadingAddNewShippingCountry" />
            <Button v-else type="create" :label="trans('Set Changes')" @click="saveEdit" full />
        </template>
    </Dialog>

</template>
