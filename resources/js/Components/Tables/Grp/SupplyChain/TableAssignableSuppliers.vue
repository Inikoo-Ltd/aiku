<script setup lang="ts">
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

function addSupplier(supplier: AssignableSupplier) {
    router.patch(
        route("grp.models.supplier.update", supplier.id),
        { agent_id: props.agent.id },
        {
            onSuccess: () => {
                notify({
                    title: ctrans("Success!"),
                    text: ctrans("Supplier added to :agent", { agent: props.agent.name }),
                    type: "success",
                })
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
                v-if="supplier.agent_code"
                :title="ctrans('Move supplier?')"
                :description="ctrans('This supplier currently belongs to agent :agent. Moving it here will detach it from :agent.', { agent: supplier.agent_name })"
                :route-yes="{
                    name: 'grp.models.supplier.update',
                    parameters: [supplier.id],
                    method: 'patch',
                }"
                :body="{ agent_id: agent.id }"
                :success-message="ctrans('Supplier moved to :agent', { agent: agent.name })"
            >
                <template #default="{ changeModel }">
                    <Button
                        type="secondary"
                        size="xs"
                        :label="ctrans('Add')"
                        @click="changeModel"
                    />
                </template>
            </ModalConfirmation>
            <Button
                v-else
                type="secondary"
                size="xs"
                :label="ctrans('Add')"
                @click="addSupplier(supplier)"
            />
        </template>
    </Table>
</template>
