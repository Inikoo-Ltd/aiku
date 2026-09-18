<script setup lang="ts">
import { onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { faPencil, faTrash, faTrashAlt, faUndoAlt } from "@fal"

defineProps<{
    title: string
    pageHeading: object
    data: any
    organisationSlug?: string
    routes?: {
        delete?: string
        restore?: string
        force_delete?: string
    }
}>()

const waitEchoReady = (callback: Function) => {
    if (window.Echo?.connector?.pusher) {
        callback()
        return
    }
    const interval = setInterval(() => {
        if (window.Echo?.connector?.pusher) {
            clearInterval(interval)
            callback()
        }
    }, 300)
}

onMounted(() => {
    waitEchoReady(() => {
        window.Echo.join("chat-list").listen(".chatlist", () => {
            router.reload({ only: ["data"] })
        })
    })
})
</script>

<template>
    <Head :title="title" />
    <PageHeading v-if="pageHeading?.title" :data="pageHeading" />

    <Table :resource="data" :name="'agents'">
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
                {{ item.is_online ? trans("Online") : trans("Offline") }}
            </span>
        </template>

        <template #cell(is_available)="{ item }">
            <span
                class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium"
                :class="item.is_available ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
                <span
                    class="w-1.5 h-1.5 rounded-full"
                    :class="item.is_available ? 'bg-green-500' : 'bg-yellow-500'" />
                {{ item.is_available ? trans("Available") : trans("Busy") }}
            </span>
        </template>

        <template #cell(auto_accept)="{ item }">
            <span
                class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium"
                :class="item.auto_accept ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'">
                {{ item.auto_accept ? trans("Yes") : trans("No") }}
            </span>
        </template>

        <template #cell(action)="{ item }">
            <div class="flex items-center gap-2">
                <!-- Active agent: Edit + Soft Delete -->
                <template v-if="!item.is_deleted_in_org">
                    <Link v-if="item.route_edit" :href="route(item.route_edit.name, item.route_edit.parameters)">
                        <Button
                            v-tooltip="trans('Edit Agent')"
                            type="secondary"
                            :icon="faPencil"
                            size="s" />
                    </Link>

                    <ModalConfirmationDelete
                        v-if="item.route_delete"
                        :routeDelete="{ ...item.route_delete, method: 'delete' }"
                        :title="trans('Delete this agent?')"
                        :description="trans('The agent will be soft deleted and can be restored later.')">
                        <template #default="{ changeModel }">
                            <Button
                                v-tooltip="trans('Delete Agent')"
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
                        v-if="item.route_restore"
                        :routeDelete="{ ...item.route_restore, method: 'patch' }"
                        :title="trans('Restore this agent?')"
                        :description="trans('The agent will be restored and become active again.')"
                        :noLabel="trans('Restore')"
                        :cancelLabel="trans('Cancel')">
                        <template #default="{ changeModel }">
                            <Button
                                v-tooltip="trans('Restore Agent')"
                                @click="changeModel"
                                type="positive"
                                :icon="faUndoAlt"
                                size="s" />
                        </template>
                    </ModalConfirmationDelete>

                    <ModalConfirmationDelete
                        v-if="item.route_force_delete"
                        :routeDelete="{ ...item.route_force_delete, method: 'delete' }"
                        :title="trans('Permanently delete this agent?')"
                        :description="trans('This action cannot be undone. The agent will be permanently removed.')">
                        <template #default="{ changeModel }">
                            <Button
                                v-tooltip="trans('Permanently Delete')"
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
