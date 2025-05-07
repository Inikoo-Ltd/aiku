<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 06 Feb 2025 21:46:44 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus } from "@fas"
import { faCheckDouble, faShare, faCross } from "@fal"
import Modal from "@/Components/Utils/Modal.vue"
import { ref } from 'vue'
import { PalletDelivery } from "@/types/pallet-delivery"
import Icon from "@/Components/Icon.vue"
import { useFormatTime, useDaysLeftFromToday } from '@/Composables/useFormatTime'
import { routeType } from "@/types/route"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { useLocaleStore } from "@/Stores/locale";

library.add(faPlus, faCheckDouble, faShare, faCross)

const props = defineProps<{
    data: {}
    tab?: string
}>()

const openModal = ref(false)
const loading = ref(false)
const locale = useLocaleStore();
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5"></Table>
</template>
