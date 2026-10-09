<script setup lang="ts">
import { nextTick, onMounted, ref } from "vue"
import { router, useForm } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingOverlay from "@/Components/Utils/LoadingOverlay.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHammer } from "@fal"

library.add(faHammer)

type ArtefactOption = { id: number; code: string; name: string | null }

const props = defineProps<{
    customerId: number
    currencyCode: string
    artefacts?: ArtefactOption[]
    preselectedArtefactId?: number | null
}>()

const isOpen = ref(false)
const isLoadingArtefacts = ref(false)

const form = useForm({
    artefact_id: null as number | null,
    code: "",
    name: "",
    price: null as number | null,
    units: 1,
})

const open = (artefactId?: number | null) => {
    isOpen.value = !artefactId
    isLoadingArtefacts.value = true
    router.reload({
        only: ["custom_product_artefacts"],
        onSuccess: async () => {
            if (!artefactId) {
                return
            }
            await nextTick()
            if (props.artefacts?.some((option) => option.id === artefactId)) {
                form.artefact_id = artefactId
                onArtefactChange(artefactId)
                isOpen.value = true
            } else {
                notify({
                    title: ctrans("Artefact not available"),
                    text: ctrans("It already has a stock or a product, so there is nothing to make from it here."),
                    type: "warning",
                })
            }
        },
        onFinish: () => (isLoadingArtefacts.value = false),
    })
}

onMounted(() => {
    if (props.preselectedArtefactId) {
        open(props.preselectedArtefactId)
    }
})

const onArtefactChange = (artefactId: number) => {
    const artefact = props.artefacts?.find((option) => option.id === artefactId)
    form.code = artefact?.code ?? ""
    form.name = artefact?.name ?? artefact?.code ?? ""
}

const close = () => {
    isOpen.value = false
    form.reset()
    form.clearErrors()
}

const submit = () => {
    form.post(route("grp.models.customer.product_from_artefact.store", { customer: props.customerId }), {
        preserveScroll: true,
        onSuccess: () => {
            notify({
                title: ctrans("Product created"),
                text: ctrans(":code is ready to order for this customer", { code: form.code }),
                type: "success",
            })
            close()
        },
    })
}
</script>

<template>
    <div>
        <Button @click="open()" :label="ctrans('Custom product')" style="secondary" icon="fal fa-hammer" />

        <Modal :isOpen="isOpen" @onClose="close" width="w-full max-w-xl">
            <div class="p-6 relative">
                <LoadingOverlay :is-loading="form.processing" position="absolute" />
                <h2 class="text-lg font-medium text-gray-900">{{ ctrans("Custom product from an artefact") }}</h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ ctrans("Pick the artefact made in production. Its stock, trade unit and SKO are created, with a product only this customer can buy.") }}
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="text-xs font-medium text-gray-600">{{ ctrans("Artefact") }}</label>
                        <Select v-model="form.artefact_id" :options="artefacts ?? []" optionValue="id" filter
                            :filterFields="['code', 'name']" :loading="isLoadingArtefacts"
                            :placeholder="ctrans('Select an artefact')" :emptyMessage="ctrans('No artefacts waiting for a trade unit')"
                            class="w-full" @update:modelValue="onArtefactChange">
                            <template #value="{ value }">
                                <span v-if="value">{{ artefacts?.find((option) => option.id === value)?.code }}</span>
                                <span v-else class="text-gray-400">{{ ctrans("Select an artefact") }}</span>
                            </template>
                            <template #option="{ option }">
                                <span class="font-medium">{{ option.code }}</span>
                                <span class="ml-2 text-gray-500">{{ option.name }}</span>
                            </template>
                        </Select>
                        <p v-if="form.errors.artefact_id" class="mt-1 text-sm text-red-500">{{ form.errors.artefact_id }}</p>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="text-xs font-medium text-gray-600">{{ ctrans("Product code") }}</label>
                            <InputText v-model="form.code" class="w-full" />
                            <p v-if="form.errors.code" class="mt-1 text-sm text-red-500">{{ form.errors.code }}</p>
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-medium text-gray-600">{{ ctrans("Name") }}</label>
                            <InputText v-model="form.name" class="w-full" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-medium text-gray-600">{{ ctrans("Price") }}</label>
                            <InputNumber v-model="form.price" mode="currency" :currency="currencyCode" :min="0" class="w-full" inputClass="w-full" />
                            <p v-if="form.errors.price" class="mt-1 text-sm text-red-500">{{ form.errors.price }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600">{{ ctrans("Units per outer") }}</label>
                            <InputNumber v-model="form.units" :min="1" showButtons class="w-full" inputClass="w-full" />
                            <p v-if="form.errors.units" class="mt-1 text-sm text-red-500">{{ form.errors.units }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <Button :label="ctrans('Create product')" style="create" :loading="form.processing"
                        :disabled="!form.artefact_id || !form.code || !form.name || form.price === null" @click="submit" />
                </div>
            </div>
        </Modal>
    </div>
</template>
