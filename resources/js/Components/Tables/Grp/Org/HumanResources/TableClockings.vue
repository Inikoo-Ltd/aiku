<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 14:00:48 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { format } from "date-fns";
import Table from "@/Components/Table/Table.vue";
import PrimeImage from "primevue/image";
import { useFormatTime } from "@/Composables/useFormatTime";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Modal from "@/Components/Utils/Modal.vue";
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";
import DatePicker from "primevue/datepicker";
import axios from "axios";
import { trans } from "laravel-vue-i18n";
import { notify } from "@kyvg/vue3-notification";
import { faEdit, faTrash, faPlus } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";

library.add(faEdit, faTrash, faPlus);

const props = defineProps<{
    data: any,
    tab?: string,
    storeClockingRoute?: string,
    timesheetDate?: string
}>()

const defaultClockedAt = (): Date => {
    const now = new Date();

    if (!props.timesheetDate) {
        return now;
    }

    const [year, month, day] = props.timesheetDate.split("-").map(Number);

    return new Date(year, month - 1, day, now.getHours(), now.getMinutes(), now.getSeconds());
};

const isEditModalOpen = ref(false);
const selectedClocking = ref<any | null>(null);
const notes = ref<string>("");
const clockedAt = ref<Date | null>(null);
const isSubmitting = ref(false);
const errorMsg = ref<string | null>(null);

const isAddModalOpen = ref(false);
const newClockedAt = ref<Date | null>(null);
const newNotes = ref<string>("");
const isAddSubmitting = ref(false);
const addErrorMsg = ref<string | null>(null);

const canEdit = computed<boolean>(() => {
    if (!props.data) {
        return false;
    }

    if ("can_edit_clockings" in props.data) {
        return !!props.data.can_edit_clockings;
    }

    if ("meta" in props.data && props.data.meta && "can_edit_clockings" in props.data.meta) {
        return !!props.data.meta.can_edit_clockings;
    }

    return false;
});

const openEditModal = (clocking: any) => {
    selectedClocking.value = clocking;
    console.log(clocking);
    notes.value = typeof clocking.notes === "string" ? clocking.notes : "";
    clockedAt.value = clocking.clocked_at ? new Date(clocking.clocked_at) : null;
    isEditModalOpen.value = true;
    errorMsg.value = null;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    selectedClocking.value = null;
    notes.value = "";
    clockedAt.value = null;
    errorMsg.value = null;
};

const submitNotes = async () => {
    if (!selectedClocking.value) {
        return;
    }

    isSubmitting.value = true;
    errorMsg.value = null;

    try {
        await axios.patch(
            route("grp.models.clocking-machine.clocking.notes.update", selectedClocking.value.id),
            {
                notes: notes.value,
                clocked_at: clockedAt.value ? format(clockedAt.value, "yyyy-MM-dd'T'HH:mm:ssXXX") : null,
            }
        );

        notify({
            title: trans("Success"),
            text: trans("Notes updated successfully."),
            type: "success",
        });

        selectedClocking.value.notes = notes.value;

        if (clockedAt.value) {
            selectedClocking.value.clocked_at = clockedAt.value.toISOString();
        }

        router.reload({
            only: [props.tab || 'clockings'],
        });

        closeEditModal();
    } catch (e: any) {
        const message =
            e?.response?.data?.message ??
            trans("Failed to update notes.");

        errorMsg.value = message;

        notify({
            title: trans("Failed"),
            text: message,
            type: "error",
        });
    } finally {
        isSubmitting.value = false;
    }
};

const openAddModal = (): void => {
    newClockedAt.value = defaultClockedAt();
    newNotes.value = "";
    addErrorMsg.value = null;
    isAddModalOpen.value = true;
};

const closeAddModal = (): void => {
    isAddModalOpen.value = false;
    newClockedAt.value = null;
    newNotes.value = "";
    addErrorMsg.value = null;
};

const submitAddClocking = async (): Promise<void> => {
    if (!props.storeClockingRoute || !newClockedAt.value) {
        return;
    }

    isAddSubmitting.value = true;
    addErrorMsg.value = null;

    try {
        await axios.post(props.storeClockingRoute, {
            clocked_at: format(newClockedAt.value, "yyyy-MM-dd'T'HH:mm:ssXXX"),
            notes: newNotes.value || null,
        });

        notify({
            title: trans("Success"),
            text: trans("Clocking added successfully."),
            type: "success",
        });

        router.reload({
            only: [props.tab || "clockings", "timesheet"],
        });

        closeAddModal();
    } catch (e: any) {
        const message =
            e?.response?.data?.message ??
            trans("Failed to add clocking.");

        addErrorMsg.value = message;

        notify({
            title: trans("Failed"),
            text: message,
            type: "error",
        });
    } finally {
        isAddSubmitting.value = false;
    }
};

</script>

<template>
    <div>
        <div v-if="canEdit && storeClockingRoute" class="flex justify-end mb-3">
            <Button
                type="secondary"
                size="xs"
                :icon="faPlus"
                :label="trans('Add clocking')"
                @click="openAddModal"
            />
        </div>

        <Table :resource="data" :name="tab" class="mt-5">
            <template #cell(media_slug)="{ item }">
                <PrimeImage
                    v-if="item.photo"
                    :src="item.photo.original"
                    preview
                    imageClass="rounded-md h-10 w-10 object-cover cursor-pointer shadow"
                />
            </template>

            <template #cell(clocked_at)="{ item }">
                <div class="text-gray-500">
                    {{ useFormatTime(item.clocked_at, { formatTime: "hms" }) }}
                </div>
            </template>

            <template #cell(employee_name)="{ item }">
                <div class="text-gray-700">{{ item.employee_name ?? "-" }}</div>
            </template>

            <template #cell(clocking_machine_name)="{ item }">
                <div class="text-gray-500">{{ item.clocking_machine_name ?? "-" }}</div>
            </template>

            <template #cell(clocking_machine_qr_code)="{ item }">
                <div class="text-gray-500">{{ item.clocking_machine_qr_code ?? "-" }}</div>
            </template>

            <template v-if="canEdit" #cell(actions)="{ item }">
                <div class="flex items-center gap-x-1">
                    <Button
                        type="transparent"
                        size="xs"
                        :icon="faEdit"
                        :label="trans('Edit')"
                        @click="openEditModal(item)"
                    />
                    <ModalConfirmationDelete
                        v-if="item.delete_route"
                        :routeDelete="item.delete_route"
                        :title="trans('Delete this clocking?')"
                        :description="trans('Any working period anchored on this clocking will keep its record but lose that reference. This action cannot be undone.')"
                    >
                        <template #default="{ changeModel }">
                            <Button
                                type="cancel"
                                size="xs"
                                :icon="faTrash"
                                :label="trans('Delete')"
                                @click="changeModel(true)"
                            />
                        </template>
                    </ModalConfirmationDelete>
                </div>
            </template>
        </Table>

        <Modal
            :isOpen="isEditModalOpen"
            @onClose="closeEditModal"
            width="w-full max-w-md"
        >
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                {{ trans("Edit Clocking Notes") }}
            </h2>

            <form @submit.prevent="submitNotes" class="space-y-4">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans("Clocked At") }}
                        </label>
                        <DatePicker
                            v-model="clockedAt"
                            showTime
                            showSeconds
                            hourFormat="24"
                            showIcon
                            fluid
                            class="mt-1"
                        />
                    </div>

                    <div>
                    <label class="block text-sm font-medium text-gray-700">
                        {{ trans("Notes") }}
                    </label>
                    <textarea
                        v-model="notes"
                        rows="4"
                        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
                    <p v-if="errorMsg" class="text-sm text-red-600">
                        {{ errorMsg }}
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <Button
                        type="secondary"
                        :label="trans('Cancel')"
                        :disabled="isSubmitting"
                        @click="closeEditModal"
                    />
                    <Button
                        type="primary"
                        :label="isSubmitting ? trans('Saving...') : trans('Save')"
                        :disabled="isSubmitting"
                        nativeType="submit"
                    />
                </div>
            </form>
        </Modal>

        <Modal
            :isOpen="isAddModalOpen"
            @onClose="closeAddModal"
            width="w-full max-w-md"
        >
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                {{ trans("Add Clocking") }}
            </h2>

            <form @submit.prevent="submitAddClocking" class="space-y-4">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans("Clocked At") }}
                        </label>
                        <DatePicker
                            v-model="newClockedAt"
                            showTime
                            showSeconds
                            hourFormat="24"
                            showIcon
                            fluid
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ trans("Notes") }}
                        </label>
                        <textarea
                            v-model="newNotes"
                            rows="4"
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <p v-if="addErrorMsg" class="text-sm text-red-600">
                        {{ addErrorMsg }}
                    </p>
                </div>

                <div class="flex justify-end space-x-3">
                    <Button
                        type="secondary"
                        :label="trans('Cancel')"
                        :disabled="isAddSubmitting"
                        @click="closeAddModal"
                    />
                    <Button
                        type="primary"
                        :label="isAddSubmitting ? trans('Saving...') : trans('Save')"
                        :disabled="isAddSubmitting"
                        nativeType="submit"
                    />
                </div>
            </form>
        </Modal>
    </div>
</template>
