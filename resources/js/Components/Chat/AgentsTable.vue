<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import Table from "@/Components/Table/Table.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faPencil, faTrash, faTrashAlt, faUndoAlt } from "@fal"

withDefaults(defineProps<{
    data: any
    name?: string
    canManage?: boolean
}>(), { name: "agents", canManage: true })
</script>

<template>
    <Table :resource="data" :name="name">
        <template #cell(specialization)="{ item }">
            <span class="text-sm">
                {{
                    Array.isArray(item.specialization)
                        ? item.specialization.join(", ")
                        : item.specialization || "-"
                }}
            </span>
        </template>

        <template #cell(is_online)="{ item }">
            <span
                class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium"
                :class="item.is_online ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                <span
                    class="w-1.5 h-1.5 rounded-full"
                    :class="item.is_online ? 'bg-green-500' : 'bg-red-500'" />
                {{ item.is_online ? ctrans("Online") : ctrans("Offline") }}
            </span>
        </template>

        <template #cell(is_available)="{ item }">
            <span
                class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium"
                :class="item.is_available ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                <span
                    class="w-1.5 h-1.5 rounded-full"
                    :class="item.is_available ? 'bg-green-500' : 'bg-yellow-500'" />
                {{ item.is_available ? ctrans("Available") : ctrans("Busy") }}
            </span>
        </template>

        <template #cell(auto_accept)="{ item }">
            <span
                class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium"
                :class="item.auto_accept ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'">
                {{ item.auto_accept ? ctrans("Yes") : ctrans("No") }}
            </span>
        </template>

        <template #cell(action)="{ item }">
            <div class="flex items-center gap-2">
                <!-- Active agent: Edit + Soft Delete -->
                <template v-if="!item.is_deleted_in_org">
                    <Link v-if="item.route_edit && canManage" :href="route(item.route_edit.name, item.route_edit.parameters)">
                        <Button
                            v-tooltip="ctrans('Edit Agent')"
                            type="secondary"
                            :icon="faPencil"
                            size="s" />
                    </Link>

                    <ModalConfirmationDelete
                        v-if="item.route_delete && canManage"
                        :routeDelete="{ ...item.route_delete, method: 'delete' }"
                        :title="ctrans('Delete this agent?')"
                        :description="ctrans('The agent will be soft deleted and can be restored later.')">
                        <template #default="{ changeModel }">
                            <Button
                                v-tooltip="ctrans('Delete Agent')"
                                @click="changeModel"
                                type="negative"
                                :icon="faTrash"
                                size="s" />
                        </template>
                    </ModalConfirmationDelete>
                </template>

                <!-- Deleted agent: Restore + Force Delete -->
                <template v-if="item.is_deleted_in_org">
                    <ModalConfirmationDelete
                        v-if="item.route_restore && canManage"
                        :routeDelete="{ ...item.route_restore, method: 'patch' }"
                        :title="ctrans('Restore this agent?')"
                        :description="ctrans('The agent will be restored and become active again.')"
                        :noLabel="ctrans('Restore')"
                        :cancelLabel="ctrans('Cancel')">
                        <template #default="{ changeModel }">
                            <Button
                                v-tooltip="ctrans('Restore Agent')"
                                @click="changeModel"
                                type="positive"
                                :icon="faUndoAlt"
                                size="s" />
                        </template>
                    </ModalConfirmationDelete>

                    <ModalConfirmationDelete
                        v-if="item.route_force_delete && canManage"
                        :routeDelete="{ ...item.route_force_delete, method: 'delete' }"
                        :title="ctrans('Permanently delete this agent?')"
                        :description="ctrans('This action cannot be undone. The agent will be permanently removed.')">
                        <template #default="{ changeModel }">
                            <Button
                                v-tooltip="ctrans('Permanently Delete')"
                                @click="changeModel"
                                type="negative"
                                :icon="faTrashAlt"
                                size="s" />
                        </template>
                    </ModalConfirmationDelete>
                </template>
            </div>
        </template>
    </Table>
</template>
