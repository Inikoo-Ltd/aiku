<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableCollections from "@/Components/Tables/Grp/Org/Catalogue/TableCollections.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import ModalCreateModel from '@/Components/Utils/ModalCreateModel.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'


const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
    formData: {}
}>()

const isModalCreateOpen = ref(false)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
       <!--  <template #button-collection="{ action }">
            <Button
                :label="action.label"
                :icon="action.icon"
                :style="action.style"
                @click="isModalCreateOpen = true"
            />
        </template> -->
    </PageHeading>
    <TableCollections :data="data" />

    <!-- <Modal :isOpen="isModalCreateOpen" @close="isModalCreateOpen = false" width="w-full max-w-3xl">
        <ModalCreateModel
            :formData
        />
    </Modal> -->
</template>