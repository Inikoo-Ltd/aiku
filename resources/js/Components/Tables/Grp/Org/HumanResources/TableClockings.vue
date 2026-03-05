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
import { useFormatTime } from "@/Composables/useFormatTime";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Modal from "@/Components/Utils/Modal.vue";
import DatePicker from "primevue/datepicker";
import axios from "axios";
import { trans } from "laravel-vue-i18n";
import { notify } from "@kyvg/vue3-notification";
import { faEdit } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";

library.add(faEdit);

const props = defineProps<{
    data: any,
    tab?: string
}>()

const isEditModalOpen = ref(false);
const selectedClocking = ref<any | null>(null);
const notes = ref<string>("");
const clockedAt = ref<Date | null>(null);
const isSubmitting = ref(false);
const errorMsg = ref<string | null>(null);

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

</script>

<template>
    <div>
        <Table :resource="data" :name="tab" class="mt-5">
            <template #cell(media_slug)="{ item }">
            </template>

            <template #cell(clocked_at)="{ item }">
                <div class="text-gray-500">
                    {{ useFormatTime(item.clocked_at, { formatTime: "hms" }) }}
                </div>
            </template>

            <template v-if="canEdit" #cell(actions)="{ item }">
                <div class="flex">
                    <Button
                        type="transparent"
                        size="xs"
                        :icon="faEdit"
                        :label="trans('Edit')"
                        @click="openEditModal(item)"
                    />
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
                        submit
                    />
                </div>
            </form>
        </Modal>
    </div>
</template>
