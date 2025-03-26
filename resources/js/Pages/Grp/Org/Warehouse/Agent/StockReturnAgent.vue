<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { Head, useForm } from "@inertiajs/vue3"
  import PageHeading from "@/Components/Headings/PageHeading.vue"
  import { capitalize } from "@/Composables/capitalize"
  import Tabs from "@/Components/Navigation/Tabs.vue"
  import { computed, ref, watch } from 'vue'
  import type { Component } from 'vue'
  import { useTabChange } from "@/Composables/tab-change"
  import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
  import Timeline from "@/Components/Utils/Timeline.vue"
  import Button from "@/Components/Elements/Buttons/Button.vue"
  import Modal from "@/Components/Utils/Modal.vue"
  import BoxNote from "@/Components/Pallet/BoxNote.vue"
  import TablePalletReturn from "@/Components/PalletReturn/tablePalletReturn.vue"
  import TablePalletReturnPallets from "@/Components/Tables/Grp/Org/Fulfilment/TablePalletReturnPallets.vue"
  import { routeType } from '@/types/route'
  import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
  import palletReturnDescriptor from "@/Components/PalletReturn/Descriptor/PalletReturn"
  import Tag from "@/Components/Tag.vue"
  import { BoxStats, PDRNotes, PalletReturn, UploadPallet } from '@/types/Pallet'
  import BoxStatsPalletReturn from '@/Pages/Grp/Org/Fulfilment/Return/BoxStatsPalletReturn.vue'
  import UploadExcel from '@/Components/Upload/UploadExcel.vue'
  import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
  import { trans } from "laravel-vue-i18n"
  import TableStoredItemReturnStoredItems from "@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemReturnStoredItems.vue"
  import { get } from "lodash"
  import PureInput from "@/Components/Pure/PureInput.vue"
  import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
  import Popover from "@/Components/Popover.vue"
  import { Tabs as TSTabs } from "@/types/Tabs"
  import { Action } from "@/types/Action"
  import axios from "axios"
  import TableFulfilmentTransactions from "@/Components/Tables/Grp/Org/Fulfilment/TableFulfilmentTransactions.vue";
  import { notify } from "@kyvg/vue3-notification"
  import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
  import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue";
  import UploadAttachment from '@/Components/Upload/UploadAttachment.vue'
  import { Table as TableTS } from '@/types/Table'
  import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
  import { faIdCardAlt, faUser, faPaperclip, faBuilding, faEnvelope, faPhone, faMapMarkerAlt, faNarwhal, faUndo, faUndoAlt } from '@fal'
  import { library } from '@fortawesome/fontawesome-svg-core'
  import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
  import { inject } from "vue"
  import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
  library.add(faIdCardAlt, faUser, faPaperclip, faBuilding, faEnvelope, faPhone, faMapMarkerAlt, faNarwhal, faUndo, faUndoAlt )
  
  const props = defineProps<{
      title: string
      tabs: TSTabs
      pallets?: {}
      stored_items?: {}
      services?: {}
      service_list_route: routeType
      physical_goods?: {}
      attachments?: TableTS
      attachmentRoutes: {
          attachRoute: routeType
          detachRoute: routeType
      }
      physical_good_list_route: routeType
      data: {
          data: PalletReturn
      }
      history?: {}
      pageHead: PageHeadingTypes
      updateRoute: routeType
  
      interest: {
          pallets_storage: boolean
          items_storage: boolean
          dropshipping: boolean
      }
      
      upload_spreadsheet: UploadPallet
      can_edit_transactions: boolean,
      box_stats: BoxStats
      notes_data: PDRNotes[]
      route_check_stored_items : routeType
      routeStorePallet : routeType
  
      option_attach_file?: {
          name: string
          code: string
      }[]
      stored_items_count?: number
  }>()
  
  
  const locale = inject('locale', aikuLocaleStructure)
  const xstored_items_count = ref(props.stored_items_count || 0)
  
  const currentTab = ref(props.tabs.current)
  const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
  const timeline = ref({ ...props.data?.data })
  const openModal = ref(false)
  const isLoadingButton = ref<string | boolean>(false)
  const isLoadingData = ref<string | boolean>(false)
  const isModalUploadOpen = ref(false)
  const dataPGoodList = ref([])
  const dataServiceList = ref([])
  
  const formAddService = useForm({ service_id: '', quantity: 1, historic_asset_id: null })
  const formAddPhysicalGood = useForm({ outer_id: '', quantity: 1, historic_asset_id: null })
  
  const component = computed(() => {
      const components: Component = {
          pallets: TablePalletReturnPallets,
          stored_items: TableStoredItemReturnStoredItems,
          services: TableFulfilmentTransactions,
          physical_goods: TableFulfilmentTransactions,
          history: TableHistories,
          attachments: TableAttachments
      }
      return components[currentTab.value]
  })
  
  
  watch(
      props,
      (newValue) => {
          timeline.value = newValue.data.data
      },
      { deep: true }
  )
  
  
  // Tabs: Services
  const onOpenModalAddService = async () => {
      isLoadingData.value = 'addService'
      try {
          const xxx = await axios.get(
              route(props.service_list_route.name, props.service_list_route.parameters)
          )
          dataServiceList.value = xxx?.data?.data || []
      } catch (error) {
          console.error(error)
          notify({
              title: 'Something went wrong.',
              text: 'Failed to fetch Services list',
              type: 'error',
          })
      }
      isLoadingData.value = false
  }
  const onSubmitAddService = (data: Action, closedPopover: Function) => {
      const selectedHistoricAssetId = dataServiceList.value.filter(service => service.id == formAddService.service_id)[0].historic_asset_id
      
      formAddService.historic_asset_id = selectedHistoricAssetId
      isLoadingButton.value = 'addService'
  
      formAddService.post(
          route(data.route?.name, {...data.route?.parameters }),
          {
              preserveScroll: true,
              onSuccess: () => {
                  closedPopover()
                  formAddService.reset()
                  handleTabUpdate('services')
              },
              onError: (errors) => {
                  notify({
                      title: 'Something went wrong.',
                      text: 'Failed to add service, please try again.',
                      type: 'error',
                  })
              },
              onFinish: () => {
                  isLoadingButton.value = false
              }
          }
      )
  }
  
  // Tabs: Physical Goods
  const onOpenModalAddPGood = async () => {
      isLoadingData.value = 'addPGood'
      try {
          const xxx = await axios.get(
              route(props.physical_good_list_route.name, props.physical_good_list_route.parameters)
          )
          dataPGoodList.value = xxx.data.data
      } catch (error) {
          notify({
              title: 'Something went wrong.',
              text: 'Failed to fetch Physical Goods list',
              type: 'error',
          })
      }
      isLoadingData.value = false
  }
  
  const onSubmitAddPhysicalGood = (data: Action, closedPopover: Function) => {
      const selectedHistoricAssetId = dataPGoodList.value.filter(pgood => pgood.id == formAddPhysicalGood.outer_id)[0].historic_asset_id
      formAddPhysicalGood.historic_asset_id = selectedHistoricAssetId
  
      isLoadingButton.value = 'addPGood'
      formAddPhysicalGood.post(
          route( data.route?.name, data.route?.parameters ),
          {
              preserveScroll: true,
              onSuccess: () => {
                  closedPopover()
                  formAddPhysicalGood.reset()
                  isLoadingButton.value = false
                  handleTabUpdate('physical_goods')
              },
              onError: (errors) => {
                  isLoadingButton.value = false
                  notify({
                      title: 'Something went wrong.',
                      text: 'Failed to add physical good, please try again.',
                      type: 'error',
                  })
              },
              onFinish: () => {
                  isLoadingButton.value = false
              }
          }
      )
  }
  
  const isModalUploadFileOpen = ref(false)
  
  </script>
  
  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead">
      </PageHeading>
  
      wait.....
  </template>
  