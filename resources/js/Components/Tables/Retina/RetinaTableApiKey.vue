<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 06 Feb 2025 21:46:44 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { inject } from "vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
library.add(faCheck, faTimes)


const props = defineProps<{
    data: {}
    tab?: string
}>()

const locale = inject('locale', {})
</script>

<template>
    <Table :resource="data" :name="tab">
        <!-- <template #cell(created_at)="{ item }">
            <div class="text-right">
                {{ useFormatTime(item.created_at, {formatTime: 'hms'}) }}
            </div>
        </template> -->

        <template #cell(actions)="{ item }">
            <ModalConfirmationDelete
                :routeDelete="item.route_delete_token"
                :title="trans('Are you sure you want to delete this access token?')"
                isFullLoading
            >
                <template #default="{ isOpenModal, changeModel }">
                    <Button
                        icon="fal fa-trash-alt"
                        type="negative"
                        @click="changeModel"
                        size="xs"
                    />
                </template>
            </ModalConfirmationDelete>
        </template>
    </Table>
</template>
