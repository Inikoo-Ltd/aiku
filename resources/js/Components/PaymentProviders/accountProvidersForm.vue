<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 10 Apr 2024 16:09:45 Central Indonesia Time, Sanur , Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faPlus } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"

import Schemaform from '@/Components/SchemaForm/SchemaForm.vue';

library.add(faPlus)

const props = defineProps<{
    provider: object,
    onCloseModal: Function,
    paymentAccountTypes: object
    organisation_id: string
}>()


</script>


<template>
    <div class="p-2">
        <Schemaform
            :blueprint="provider?.formData?.blueprint"
            :route="route(provider?.formData?.route.name, {organisation : props.organisation_id, ...provider?.formData?.route.parameters})"
            @onSuccess="()=>props.onCloseModal()"
            @onCancel="()=>props.onCloseModal()"
        />
    </div>
</template>
