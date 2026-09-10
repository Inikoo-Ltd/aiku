<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 09 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import EditModel from "@/Pages/Grp/EditModel.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

defineProps<{
    title: string
    pageHead: object
    formData: object
    delete_route?: routeType
    number_artefacts?: number
}>()
</script>

<template>
    <EditModel :title="title" :pageHead="pageHead" :formData="formData" />

    <div v-if="delete_route" class="mt-6 rounded-lg border border-red-200 px-4 py-3">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <div>
                <div class="font-medium">{{ ctrans('Delete this family') }}</div>
                <div class="text-sm text-gray-500">
                    {{ ctrans('The artefacts stay in the department, they are left without a family.') }}
                </div>
            </div>

            <ModalConfirmationDelete
                class="ml-auto"
                :routeDelete="delete_route"
                :title="ctrans('Delete this family?')"
                :description="ctrans('The family is deleted for good. This cannot be undone.')"
                :noLabel="ctrans('Yes, delete the family')">
                <template #default="{ changeModel }">
                    <Button type="negative" size="xs" icon="far fa-trash-alt" :label="ctrans('Delete family')" @click="changeModel" />
                </template>

                <template #warning>
                    <div v-if="number_artefacts" class="mt-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                        <div class="font-semibold">
                            {{ number_artefacts === 1
                                ? ctrans('1 artefact will be left without a family')
                                : ctrans(':count artefacts will be left without a family', { count: number_artefacts }) }}
                        </div>
                        <div class="mt-1">
                            {{ ctrans('They stay in the department, you will have to put them in another family yourself.') }}
                        </div>
                    </div>
                </template>
            </ModalConfirmationDelete>
        </div>
    </div>
</template>
