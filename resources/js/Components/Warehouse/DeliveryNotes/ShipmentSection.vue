<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 19 Jul 2025 11:19:28 British Summer Time, Trnava, Slovakia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { useTruncate } from '@/Composables/useTruncate'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import { routeType } from '@/types/route'
import axios from 'axios'

const props = defineProps<{
  shipments: {
    id: number
    name: string
    tracking: string
    label?: string
    label_type?: string
    combined_label_url?: string
    is_printable?: boolean
    formatted_tracking_urls: {
      url: string
      tracking: string
    }[]
  }[]
}>()

// Shipment deletion
const isDeleteShipment = ref<number | null>(null)
const onDeleteShipment = (idShipment: number) => {
  router.delete(route(props.deleteRoute.name, {
    ...props.deleteRoute.parameters,
    shipment: idShipment,
  }),
  {
    preserveScroll: true,
    onStart: () => {
      isDeleteShipment.value = idShipment
    },
    onSuccess: () => {
      notify({
        title: trans("Success!"),
        text: trans("Shipment has deleted."),
        type: "success",
      })
    },
    onError: (errors) => {
      notify({
        title: trans("Something went wrong."),
        text: trans("Failed to delete shipment. Please try again or contact administrator."),
        type: "error",
      })
    },
    onFinish: () => {
      isDeleteShipment.value = null
    },
  })
}

// PDF conversion
const base64ToPdf = (base: string) => {
  // Convert base64 to byte array
  const byteCharacters = atob(base)
  const byteNumbers = Array.from(byteCharacters, char => char.charCodeAt(0))
  const byteArray = new Uint8Array(byteNumbers)

  // Create a Blob and generate object URL
  const blob = new Blob([byteArray], { type: 'application/pdf' })
  const blobUrl = URL.createObjectURL(blob)

  // Create a temporary link to trigger download
  const link = document.createElement('a')
  link.href = blobUrl
  link.download = 'file.pdf'
  link.click()

  // Clean up the object URL
  URL.revokeObjectURL(blobUrl)
}

// Print shipment
const isLoadingPrint = ref(false)
const onPrintShipment = async (ship) => {
  isLoadingPrint.value = true
  try {
    const response = await axios.post(
      route(
        'grp.models.printing.shipment.label',
        {
          shipment: ship.id
        }
      )
    )

    if (response.data.state === 'queued') {
      notify({
        title: trans("Got it!"),
        text: trans("Your shipment label is queued for printing."),
        type: "info",
      })
    } else if (response.data.state === 'error') {
      throw new Error(response.data.message || 'Failed to print shipment label.')
    }

  } catch (error) {
    notify({
      title: trans("Something went wrong."),
      text: trans("Failed to print shipment label. Please try again or contact administrator."),
      type: "error",
    })
  } finally {
    isLoadingPrint.value = false
  }
}
</script>

<template>
  <div v-if="shipments?.length" class="flex gap-x-1 py-0.5">
    <div class="group w-full">
      <div class="leading-4 xtext-base flex justify-between w-full py-1">
        <div>{{ trans("Shipments") }}</div>
      </div>

      <ul v-if="shipments" class="list-none">
        <li v-for="(shipment, shipmentIdx) in shipments" :key="shipmentIdx"
          class="hover:bg-gray-100 tabular-nums ">
          <div class="flex justify-between">
            {{ shipment.name }}
            <div v-if="shipment.formatted_tracking_urls && shipment.formatted_tracking_urls.length > 0">
              <div v-for="(trackingItem, trackingIndex) in shipment.formatted_tracking_urls" :key="trackingIndex" class="text-sm">
                <a :href="trackingItem.url" target="_blank" class="secondaryLink">
                  {{ trackingItem.tracking }}
                </a>
              </div>
            </div>
            <a v-if="shipment.combined_label_url" v-tooltip="trans('Click to open file')"
              target="_blank" :href="shipment.combined_label_url" class="">
              <FontAwesomeIcon icon="fal fa-barcode-read"
                class="text-gray-400 hover:text-gray-600" fixed-width aria-hidden="true" />
            </a>

            <div v-else-if="shipment.label && shipment.label_type === 'pdf'"
              v-tooltip="trans('Click to download file')" @click="base64ToPdf(shipment.label)"
              class="group cursor-pointer">
              <span class="truncate">
                {{ shipment.name }}
              </span>
              <span v-if="shipment.tracking" class="text-gray-400">
                ({{ useTruncate(shipment.tracking, 14) }})
              </span>
              <FontAwesomeIcon icon="fal fa-external-link"
                class="text-gray-400 group-hover:text-gray-700" fixed-width
                aria-hidden="true" />
            </div>

            <div v-else>
              <span class="truncate">
                {{ shipment.name }}
              </span>
              <span v-if="shipment.tracking" class="text-gray-400">
                ({{ useTruncate(shipment.tracking, 14) }})
              </span>
            </div>

            <div v-if="isDeleteShipment === shipment.id" class="px-1">
              <LoadingIcon />
            </div>
            <ModalConfirmationDelete v-else :routeDelete="{
              name: 'grp.models.shipment.delete',
              parameters: {

                shipment: shipment.id,
              }
            }" :title="trans('Are you sure you want to delete this shipment (:ship)?', { ship: shipment.name })"
              isFullLoading>
              <template #default="{ isOpenModal, changeModel }">
                <div @click="changeModel">
                  <FontAwesomeIcon icon="fal fa-times" class="text-red-400 hover:text-red-600"
                    fixed-width aria-hidden="true" />
                </div>
              </template>
            </ModalConfirmationDelete>
          </div>
          <Button v-if="shipment.is_printable" @click="() => onPrintShipment(shipment)" size="xs"
            icon="fal fa-print" label="Print label" type="tertiary" :loading="isLoadingPrint" />
        </li>
      </ul>
    </div>
  </div>
</template>