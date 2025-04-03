<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 25 Jan 2024 11:46:16 Malaysia Time, Bali Office, Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { FulfilmentCustomer } from "@/types/Customer"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useFormatTime } from "@/Composables/useFormatTime";
import { useLocaleStore } from "@/Stores/locale";
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue";
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faCheckCircle } from '@fas'
import { faTimesCircle } from '@fal'
import { trans } from 'laravel-vue-i18n'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import ModalRejected from '@/Components/Utils/ModalRejected.vue'
import { ref } from 'vue'
import ModalApproveConfirmation from '@/Components/Utils/ModalApproveConfirmation.vue'


library.add(faCheck, faTimes, faCheckCircle, faTimesCircle)

const props = defineProps<{
  data: {}
  tab?: string
}>()

</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5">

  </Table>

</template>