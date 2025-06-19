<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import Table from '../Table/Table.vue'
import ModalConfirmationDelete from '../Utils/ModalConfirmationDelete.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import Button from '../Elements/Buttons/Button.vue'

const props = defineProps<{
    data: {}
    tab: string
}>()
</script>

<template>
    <div>
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
    </div>
</template>