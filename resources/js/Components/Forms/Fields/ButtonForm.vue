<script setup lang="ts">


import { ref } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAsterisk } from "@fas"
import { faBroadcastTower } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Icon from '@/Components/Icon.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import { routeType } from '@/types/route'
import { faPowerOff } from '@far'
library.add(faAsterisk, faBroadcastTower,faPowerOff)

defineOptions({ inheritAttrs: false })

// const props = defineProps(['form', 'fieldName', 'fieldData'])
const props = defineProps<{
    form: any,
    fieldName: string,
    fieldData: {
        label_button: string,
        icon: string,
        type_button: string
        route: routeType
        confirmation?: {
            title?: string
            description?: string
            confirm?: string
            cancel?: string
        }
    }

}>()



</script>

<template>
    <ModalConfirmation
        v-if="fieldData.confirmation"
        :title="fieldData.confirmation.title"
        :description="fieldData.confirmation.description"
        :noLabel="fieldData.confirmation.cancel"
        :routeYes="fieldData.route"
    >
        <template #default="{ changeModel }">
            <Button
                :icon="fieldData.icon"
                :label="fieldData.label_button"
                :type="fieldData.type_button"
                @click="changeModel"
            />
        </template>
        <template #btn-yes="{ isLoadingdelete, clickYes }">
            <Button
                :loading="isLoadingdelete"
                :type="fieldData.type_button"
                :label="fieldData.confirmation.confirm"
                @click="clickYes"
            />
        </template>
    </ModalConfirmation>

    <ButtonWithLink v-else :icon="fieldData.icon" :label="fieldData.label_button" :type="fieldData.type_button" :routeTarget="fieldData.route" />
</template>
