<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import Button from "@/Components/Elements/Buttons/Button.vue"
  import { trans } from "laravel-vue-i18n"
  import SelectQuery from "@/Components/SelectQuery.vue"
  import { notify } from "@kyvg/vue3-notification"
  import axios from "axios"
  import { get } from 'lodash-es'
  import { routeType } from "@/types/route"
  import { ref } from 'vue'
  import Tag from '@/Components/Tag.vue'
  import { library } from "@fortawesome/fontawesome-svg-core"
  import { faPlus, faChevronDown, faTimes, faMinus, faSparkles } from "@fas"
  import { faTrashAlt, faExclamationTriangle } from "@far"
  import { faAsterisk } from "@fas"
  import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
  import InputNumber from "primevue/inputnumber"
  import LoadingIcon from "./Utils/LoadingIcon.vue"
  import PureInput from "@/Components/Pure/PureInput.vue"
  
  library.add(faAsterisk, faPlus, faChevronDown, faTimes, faMinus, faTrashAlt, faSparkles, faExclamationTriangle)
  
  const props = defineProps<{
      storedItemsRoute: {
          store: routeType
          index: routeType
          delete: routeType
      }
      form: {}
      stored_items: {}[]
      title?: string
      prefixQuery?: string   // from filter[global] to stored_items_filter[global]
  }>()
  
  const loadingAddStoredItem = ref(false)
  const newStoredItem = ref(null)
  const messageMode = ref(false)
  const disabledSelect = ref({
      edit: props.form.id ? true : false,
      disabled: props.form.id ? true : false,
  })
  const _selectQuery = ref(null)
  
  const emits = defineEmits<{
      (e: 'onSave', event: any): void
      (e: 'closeModal'): void
  }>()
  
  const createStoredItems = async (option, select) => {
      loadingAddStoredItem.value = true
      try {
          const response: any = await axios.post(
              route(props.storedItemsRoute.store.name, props.storedItemsRoute.store.parameters),
              { reference: option.id },
              { headers: { "Content-Type": "multipart/form-data" } }
          )
          props.form.errors = {}
          props.form.id = response.data.id
          disabledSelect.value.disabled = true
          newStoredItem.value = response.data
          _selectQuery.value._multiselectRef.close()
          loadingAddStoredItem.value = false
      } catch (error: any) {
          props.form.errors.id = error.response.data.message
          _selectQuery.value._multiselectRef.close()
          loadingAddStoredItem.value = false
          notify({
              title: "Failed to add new stored items",
              text: error.message ? error.message : 'failed to create stored item',
              type: "error",
          })
          return false
      }
  }
  
  // Section: Delete stored items
  const isDeleteStoredItem = ref(false)
  const deleteStoredItems = async (closeModal : boolean) => {
      isDeleteStoredItem.value = true
      try {
          const response: any = await axios.delete(
              route(props.storedItemsRoute.delete.name, {storedItem : props.form.id}),
          )
          props.form.errors = {}
          props.form.id = null
          isDeleteStoredItem.value = false
          newStoredItem.value = null
          if(closeModal) emits('closeModal')
          else disabledSelect.value.disabled = false
      } catch (error: any) {
          console.log(error)
          props.form.errors.id = error?.response?.data?.message
          isDeleteStoredItem.value = false
          notify({
              title: "Failed to add new stored items",
              text: error.message ? error.message : 'failed to create stored item',
              type: "error",
          })
          return false
      }
  }
  
  
  const filterOptionsStoredItems = (e) => {
      if (!props.form.id) {
          return e.filter((item) =>
              !props.stored_items?.map((storedItem) => storedItem.id)?.includes(item.id)
          );
      }
      return e;
  };
  
  const incrementQuantity = () => {
      props.form.quantity = Number(props.form.quantity) + 1;
  }
  
  const decrementQuantity = () => {
      if (props.form.quantity > 1) {
          props.form.quantity = Number(props.form.quantity) - 1;
      }
  }
  
  const onCancel = () =>{
      if(newStoredItem.value && !disabledSelect.value.edit) messageMode.value = true
      else emits('closeModal')
  }
  
  const onSaved = async () => {
      onSaveNameForNewStoredItem()
      let newData = []
  
      if (props.form.oldData) {
          const index = props.stored_items.findIndex((item) => item.id === props.form.oldData.id)
          if (index !== -1) {
              const updatedStoredItems = [...props.stored_items]
              updatedStoredItems.splice(index, 1, props.form.data())
              newData = updatedStoredItems
          }
      } else {
          newData = [...props.stored_items, { ...props.form.data() }]
      }
  
      const finalData = {}
      newData.forEach((d) => {
          finalData[d.id] = { quantity: Math.floor(d.quantity) }
      })
  
      emits("onSave", finalData)
  }
  
  // Section: save name for new stored item
  const newStoredItemName = ref('')
  const errorNewStoredItemName = ref('')
  const onSaveNameForNewStoredItem = async () => {
      if (!newStoredItem.value?.id || !newStoredItemName.value) {
          return
      }
  
      try {
          const response: any = await axios.patch(
              route('retina.models.stored-items.update', [newStoredItem.value?.id]),
              { name: newStoredItemName.value }
          )
      } catch (error: any) {
          console.log(error)
          errorNewStoredItemName.value = error?.response?.data?.message
          
          notify({
              title: trans("Something went wrong."),
              text: error.message ? error.message : trans('Failed to set name for new stored item'),
              type: "error",
          })
      }
  }
  </script>
  
  <template>
      <div v-if="!messageMode">
          <div class="text-center font-semibold text-2xl mb-4">
              {{ title ? title : disabledSelect.edit ? trans("Edit customer's SKUs") : trans("Set up Customer's SKUs") }}
          </div>
          <div class="grid grid-cols-3 gap-x-4">
              <label class="mt-1 block text-sm font-medium text-gray-700">{{ trans("Reference") }}</label>
              
              <div class="mt-1 col-span-2">
                  <SelectQuery ref="_selectQuery"
                      :filterOptions="filterOptionsStoredItems"
                      :urlRoute="route(storedItemsRoute.index.name, storedItemsRoute.index.parameters)"
                      :value="form"
                      :placeholder="'Select or add item'"
                      :required="true"
                      :trackBy="'reference'"
                      :label="'reference'"
                      :valueProp="'id'"
                      :closeOnSelect="true"
                      :clearOnSearch="false"
                      :prefixQuery
                      :fieldName="'id'"
                      :createOption="false"
                      :onCreate="createStoredItems"
                      @afterCreate="(value, option) => form['id'] = value"
                      :disabled="disabledSelect.disabled"
                      @updateVModel="() => form.errors.id = ''"
                      :loadingCaret="loadingAddStoredItem"
                  >
                      <template #nooptions="{ search }: { search: string }">
                          <div class="px-2 py-3" @click="() => createStoredItems({ id: search, reference: search }, [])">
                              <font-awesome-icon v-if="search !== '' || search" :icon="['fas', 'plus']" class="mr-3" />
                              {{ search !== "" || search ? `${trans(`Create`)} : ${search}` : trans("No Result") }}
                          </div>
                      </template>
              
                      <template #afterlist="{ search, options }: { search: string, options: any[] }">
                          <!-- {{ options }} -->
                          <div v-if="search && options?.length" class="border-t border-gray-300">
                              <!-- {{ [...options.options.map(options => options.reference)] }} === -->
                              <div v-if="!options?.some(option => option.reference === search)" class="bg-indigo-100 hover:bg-indigo-200 px-2 py-3" @click="() => createStoredItems({ id: search, reference: search }, [])">
                                  <font-awesome-icon :icon="['fas', 'plus']" class="mr-3" />
                                  {{ `${trans(`Create`)}: ` }} <Tag :label="search" no-hover-color /> <br>
                              </div>
                          </div>
                      </template>
              
                      <template #option="{option, isSelected, isPointed, search, label}">
                          <div v-html="option[label]?.replace(search, `<span style='background: #eded02'>${search}</span>`)"></div>
                      </template>
              
                      <template #noresults="{ search }: { search: string }">
                          <div class="px-2 py-3" @click="() => createStoredItems({ id: search, reference: search }, [])">
                              <font-awesome-icon :icon="['fas', 'plus']" class="mr-3" />
                              {{ `${trans(`Create`)} : ${search}` }}
                          </div>
                      </template>
                      
                      <template v-if="!disabledSelect.edit" #caret="{ handleCaretClick, isOpen }">
                          <div class="px-2">
                              <LoadingIcon v-if="isDeleteStoredItem" class="text-red-500 text-sm mr-1" />
                              <font-awesome-icon v-else-if="!disabledSelect.disabled" :icon="['fas', 'chevron-down']"
                                  class="text-xs mr-2" />
                              <font-awesome-icon v-else :icon="faTrashAlt" class="text-xs mr-2 text-red-300 hover:text-red-600"
                                  @click="deleteStoredItems(false)" />
                          </div>
                      </template>
              
                      <template #singlelabel="{ value }">
                          <div v-if="!loadingAddStoredItem" class="flex justify-start w-full px-2 gap-3">
                              {{ value["reference"] }}
                              <Tag label="New" :theme="4" v-if="newStoredItem" >
                                  <template #label>
                                      <font-awesome-icon  :icon="faSparkles" v-tooltip="'New Stored Item'" class="text-xs  text-yellow-500"/>
                                      New
                                  </template>
                              </Tag>
                          </div>
                          <div v-else class="flex justify-start w-full px-2 gap-3">
                              <LoadingIcon />
                          </div>
                      </template>
                  </SelectQuery>
              </div>
          </div>
          <p v-if="get(form, ['errors', 'id'])" class="mt-2 text-sm text-red-500">
              {{ form.errors.id }}
          </p>
  
          <!-- Section: input Name -->
          <div v-if="form.id && newStoredItem" class="mt-4 grid grid-cols-3 gap-x-4">
              <label class="mt-1 text-sm font-medium text-gray-700 inline-flex items-start py-0">
                      <span class="leading-none">{{ trans("Name") }}</span>
                      <FontAwesomeIcon
                          icon="fas fa-asterisk"
                          class="ml-1 font-light text-[8px] text-red-400 mr-1 opacity-75" />
              </label>
              
              <PureInput
                  v-model="newStoredItemName"
                  @update:modelValue="() => errorNewStoredItemName = ''"
                  :class="errorNewStoredItemName ? 'errorShake' : ''"
                  class="col-span-2"
                  :placeholder="trans('Customer\'s SKU name')"
              />
          </div>
          <p v-if="errorNewStoredItemName" class="mt-2 text-sm text-red-500">
              {{ errorNewStoredItemName }}
          </p>
  
  
          <!-- Quantity: Input number -->
          <div class="mt-4 grid grid-cols-3 gap-x-4">
              <label class="mt-1 block text-sm font-medium text-gray-700">{{ trans("Quantity") }}</label>
              <!-- 
              <div class=" mt-1 flex items-center gap-2">
                  <input v-model="form.quantity" id="quantity" name="quantity" :autofocus="true" type="number"
                      autocomplete="quantity" :required="true" :min="1" @update:modelValue="form.errors.quantity = ''"
                      class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                  <Button type="tertiary" :icon="faPlus" @click="incrementQuantity" />
                  <Button type="tertiary" :icon="faMinus" @click="decrementQuantity" />
              </div> -->
  
              <div class="col-span-2 self-end w-full justify-end flex gap-x-2">
                  <div
                      @click="decrementQuantity"
                      class="leading-4 cursor-pointer inline-flex items-center gap-x-2 font-medium focus:outline-none disabled:cursor-not-allowed min-w-max bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-200/70 disabled:bg-gray-200/70 rounded px-1 py-1.5 text-base justify-self-center"
                  >
                      <FontAwesomeIcon icon='fas fa-minus' class='' fixed-width aria-hidden='true' />
                  </div>
                  <div class="text-center tabular-nums">
                      <!-- <Transition name="spin-to-right">
                          <span :key="data.audited_quantity" class="text-lg" :class="data.audited_quantity > data.quantity ? 'text-green-500' : data.audited_quantity === data.quantity ? 'text-gray-500' : 'text-red-500'">
                              {{ data.audited_quantity }}
                          </span>
                      </Transition> -->
                      <InputNumber
                          v-model="form.quantity"
                          @update:modelValue="form.errors.quantity = ''"
                          buttonLayout="horizontal"
                          :min="0"
                          style="width: 100%"
                          :inputStyle="{
                              padding: '0px',
                              width: '50px',
                              fontSize: '1em'
                          }"
                      />
                  </div>
                  <div
                      @click="incrementQuantity"
                      class="leading-4 cursor-pointer inline-flex items-center gap-x-2 font-medium focus:outline-none disabled:cursor-not-allowed min-w-max bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-200/70 disabled:bg-gray-200/70 rounded px-1 py-1.5 text-base justify-self-center"
                  >
                      <FontAwesomeIcon icon='fas fa-plus' class='' fixed-width aria-hidden='true' />
                  </div>
              </div>
          </div>
  
          <p v-if="get(form, ['errors', 'quantity'])" class="mt-2 text-sm text-red-600">
              {{ form.errors.quantity }}
          </p>
      </div>
  
      <div v-else>
          <div class="flex justify-center mb-6"><font-awesome-icon :icon="['far', 'exclamation-triangle']" class="text-8xl text-yellow-500"/></div>
          
          <div class="text-center font-semibold text-2xl mb-6">
              {{ trans('Do you want to delete') }} {{ newStoredItem?.reference }} ?
          </div>
      </div>
  
      <div v-if="!messageMode" class="flex gap-3 mt-5">
          <Button type="tertiary" :label="trans('Cancel')" @click="onCancel" class="select-none" />
          <Button
              full
              @click="onSaved"
              type="save"
              :loading="props.form.processing"
              :disabled="!(props.form.id || props.form.oldData?.id) || (newStoredItem ? !newStoredItemName : false)"
              v-tooltip="(newStoredItem && !newStoredItemName ? 'Complete name to save' : '')"
          />
      </div>
  
      <div v-else class="grid grid-cols-2 gap-3">
          <div class="col-span-1 relative">
              <Button full type="tertiary" label="No" @click="()=>emits('closeModal')"></Button>
          </div>
          <div class="col-span-1">
              <Button full @click="deleteStoredItems(true)" label="Yes" :loading="props.form.processing"></Button>
          </div>
      </div>
  
  </template>
  
  <style scoped>
  :deep(.p-inputtext) {
      padding: 0.5rem;
      font-size: 0.875rem;
      border: 1px solid transparent;
      border-bottom: 1px solid rgb(192, 192, 192);
      background-color: transparent;
      border-radius: 0;
      box-shadow: 0;
      text-align: center;
  }
  
  :deep(.p-inputtext:enabled:hover) {
      border: 1px solid transparent;
      border-bottom: 1px solid rgb(192, 192, 192);
  }
  
  :deep(.p-inputtext:enabled:focus) {
      border: 1px solid transparent;
      border-bottom: 1px solid rgb(192, 192, 192);
  }
  </style>