<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"
import { ctrans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"

interface AssignableSupplier {
    id: number
    code: string
    name: string
    location: object
    agent_code: string | null
    agent_name: string | null
    organisations_joining_agent: string | null
}

const props = defineProps<{
    data: object
    agent: {
        id: number
        code: string
        name: string
    }
    tab?: string
}>()

const addingSupplierId = ref<number | null>(null)

function confirmationDescription(supplier: AssignableSupplier): string {
    const lines = [
        supplier.agent_code
            ? ctrans("This supplier currently belongs to agent :current. Adding it here moves it off :current to :agent.", { current: supplier.agent_name, agent: props.agent.name })
            : ctrans("This free supplier will be bought through :agent.", { agent: props.agent.name }),
    ]

    if (supplier.organisations_joining_agent) {
        lines.push(ctrans("These organisations don't trade with :agent yet. They will be set up with :agent and keep buying from this supplier through it: :organisations.", { agent: props.agent.name, organisations: supplier.organisations_joining_agent }))
    }

    return lines.join(" ")
}

function addSupplier(supplier: AssignableSupplier, closeModal: () => void) {
    router.patch(
        route("grp.models.supplier.update", supplier.id),
        { agent_id: props.agent.id },
        {
            preserveScroll: true,
            onStart: () => {
                addingSupplierId.value = supplier.id
            },
            onSuccess: () => {
                closeModal()
                notify({
                    title: ctrans("Success!"),
                    text: ctrans(":supplier added to :agent", { supplier: supplier.code, agent: props.agent.name }),
                    type: "success",
                })
            },
            onError: (errors) => {
                notify({
                    title: ctrans("Something went wrong"),
                    text: Object.values(errors).join(", "),
                    type: "error",
                })
            },
            onFinish: () => {
                addingSupplierId.value = null
            },
        }
    )
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(agent_code)="{ item: supplier }">
            {{ supplier.agent_code ?? ctrans("Free") }}
        </template>
        <template #cell(location)="{ item: supplier }">
            <AddressLocation :data="supplier.location" />
        </template>
        <template #cell(actions)="{ item: supplier }">
            <ModalConfirmation
                :title="supplier.agent_code ? ctrans('Move supplier?') : ctrans('Add supplier?')"
                :description="confirmationDescription(supplier)"
            >
                <template #default="{ changeModel }">
                    <Button
                        type="secondary"
                        size="xs"
                        :label="ctrans('Add')"
                        @click="changeModel"
                    />
                </template>
                <template #btn-yes="{ closeModal }">
                    <Button
                        :loading="addingSupplierId === supplier.id"
                        :label="ctrans('Yes, add')"
                        @click="addSupplier(supplier, closeModal)"
                    />
                </template>
            </ModalConfirmation>
        </template>
    </Table>
</template>
