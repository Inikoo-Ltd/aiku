<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { Link, router } from '@inertiajs/vue3'
  import Table from '@/Components/Table/Table.vue'
  import { Product } from "@/types/product"
  import Icon from "@/Components/Icon.vue"

  import { remove as loRemove } from 'lodash-es'

  import { library } from "@fortawesome/fontawesome-svg-core"
  import { faConciergeBell, faGarage, faExclamationTriangle, faPencil } from '@fal'
  import { routeType } from '@/types/route'
  import Button from '@/Components/Elements/Buttons/Button.vue'
  import { onMounted, onUnmounted, ref } from 'vue'
  import Tag from '@/Components/Tag.vue'
  import axios from 'axios'
  import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
  import { notify } from '@kyvg/vue3-notification'
  import Multiselect from '@vueform/multiselect'
  library.add(faConciergeBell, faGarage, faExclamationTriangle, faPencil)


  const props = defineProps<{
      data: {}
      tab?: string,
      routes: {
          dataList: routeType
          submitAttach: routeType
          detach: routeType
      },
      tagsList: tag[],
      tagRoute?: {}
  }>()

  interface tag {
      id: number
      slug: string
      name: string
      type: string
  }

  function productRoute(product: Product) {
      switch (route().current()) {
          case 'retina.dropshipping.products.index':
              return route(
                  'retina.dropshipping.products.show',
                  [product.slug])
          case 'retina.dropshipping.portfolios.index':
          case 'retina.dropshipping.customer_sales_channels.portfolios.index':

              if(product.type == 'StoredItem') {
                  return route(
                      'retina.fulfilment.itemised_storage.stored_items.show',
                      [product.slug])
              }

              return route(
              'retina.dropshipping.portfolios.show',
              [product.slug])

          case 'grp.overview.catalogue.products.index':
              return route(
                  'grp.org.shops.show.catalogue.products.current_products.show',
                  [product.organisation_slug, product.shop_slug, product.slug])
          default:
              return null
      }
  }

  const tagsListTemp = ref<tag[]>(props.tagsList)
  const onEditProduct = ref(false)

  const isLoadingDetach = ref<string[]>([])

  // Add new Tag
  const addNewTag = async (option: tag, idProduct: number) => {
      // console.log('option', option, idLocation)
      try {
          const response: any = await axios.post(route('grp.models.product.tag.store', idProduct),
              { name: option.name },
              {
                  headers: { "Content-Type": "multipart/form-data" },
              }
          )
          tagsListTemp.value.push(response.data.data)  // (manipulation) Add new data to reactive data
          // return option
      } catch (error: any) {
          notify({
              title: "Failed to add new tag",
              text: error,
              type: "error"
          })
          // return false
      }
  }

  // On update data Tags (add tag or delete tag)
  const updateTagItemTable = async (tags: string[], idProduct: number) => {
      try {
          await axios.patch(route('grp.models.product.tag.attach', idProduct),
              { tags: tags },
          )

          // Refetch the data of Table to update the item.tags (v-model doesn't work)
          router.reload(
              {
                  only: ['products']
              }
          )
      } catch (error: any) {
          notify({
              title: "Failed to update tag",
              text: error,
              type: "error"
          })
          return false
      }
  }

  onMounted(() => {
      if (typeof window !== 'undefined') {
          document.addEventListener('keydown', (e) => e.keyCode == 27 ? onEditProduct.value = false : '')
      }
  })

  onUnmounted(() => {
      document.removeEventListener('keydown', () => false)
  })

  </script>

  <template>
      <Table :resource="data" :name="tab" class="mt-5">
          <template #cell(state)="{ item: product }">
              <Icon :data="product.state"> </Icon>
          </template>

          <template #cell(slug)="{ item: product }">
                  {{ product['slug'] }}
          </template>

          <template #cell(shop_code)="{ item: product }">
                  {{ product['shop_slug'] }}
          </template>

          <template #cell(actions)="{ item }">
              <Link
                  v-if="routes?.detach?.name"
                  as="button"
                  :href="route(routes.detach.name, routes.detach.parameters)"
                  :method="routes.detach.method"
                  :data="{
                      product: item.id
                  }"
                  preserve-scroll
                  @start="() => isLoadingDetach.push('detach' + item.id)"
                  @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)"
              >
                  <Button
                      icon="fal fa-times"
                      type="negative"
                      size="xs"
                      :loading="isLoadingDetach.includes('detach' + item.id)"
                  />
              </Link>
              <Link
                  :v-else="item?.delete_product?.name"
                  as="button"
                  :href="route(item.delete_product.name, item.delete_product.parameters)"
                  :method="item.delete_product.method"
                  :data="{
                      product: item.id
                  }"
                  preserve-scroll
                  @start="() => isLoadingDetach.push('detach' + item.id)"
                  @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)"
              >
                  <Button
                      icon="fal fa-times"
                      type="negative"
                      size="xs"
                      :loading="isLoadingDetach.includes('detach' + item.id)"
                  />
              </Link>
          </template>

          <template #cell(tags)="{ item }">
              <div class="min-w-[200px] relative p-0">
                  <div v-if="onEditProduct !== item.slug" class="flex gap-x-1 gap-y-1.5 mb-2">
                      <template v-if="item.tags?.length">
                          <Tag v-for="tag in item.tags"
                              :label="tag"
                              :stringToColor="true"
                              size="sm"
                          />
                      </template>
                      <div v-else class="italic text-gray-400">
                          No tags
                      </div>

                      <!-- Icon: pencil -->
                      <div class="flex items-center px-1" @click="() => onEditProduct = item.slug">
                          <FontAwesomeIcon icon='fal fa-pencil' class='text-gray-400 text-lg cursor-pointer hover:text-gray-500' fixed-width aria-hidden='true' />
                      </div>
                  </div>

                  <div v-else>
                      <Multiselect v-model="item.tags"
                          :key="item.id"
                          mode="tags"
                          placeholder="Select the tag"
                          valueProp="slug"
                          trackBy="slug"
                          label="name"
                          @change="(tags) => (updateTagItemTable(tags, item.id))"
                          :closeOnSelect="false"
                          searchable
                          createOption
                          :onCreate="(tag: tag) => addNewTag(tag, item.id)"
                          :caret="false"
                          :options="tagsListTemp"
                          noResultsText="No one left. Type to add new one."
                          appendNewTag
                      >
                          <template #tag="{ option, handleTagRemove, disabled }: {option: tag, handleTagRemove: Function, disabled: boolean}">
                              <div class="px-0.5 py-[3px]">
                                  <Tag
                                      :label="option.name"
                                      :closeButton="true"
                                      :stringToColor="true"
                                      size="sm"
                                      @onClose="(event) => handleTagRemove(option, event)"
                                  />
                              </div>
                          </template>
                      </Multiselect>
                      <div class="text-gray-400 italic text-xs">
                          Press Esc to finish edit or <span @click="() => onEditProduct = false" class="hover:text-gray-500 cursor-pointer">click here</span>.
                      </div>
                  </div>
              </div>
          </template>
      </Table>
  </template>

  <style src="../../../../../node_modules/@vueform/multiselect/themes/default.css"></style>

  <style lang="scss">
  .multiselect-tags-search {
      @apply focus:outline-none focus:ring-0 focus:border-none h-full #{!important}
  }

  .multiselect.is-active {
      @apply shadow-none
  }

  // .multiselect-tag {
  //     @apply bg-gradient-to-r from-lime-300 to-lime-200 hover:bg-lime-400 ring-1 ring-lime-500 text-lime-600
  // }

  .multiselect-tags-search-wrapper {
      @apply mb-0 #{!important}
  }

  .multiselect-tags {
      @apply my-0.5 #{!important}
  }

  .multiselect-tags-search {
      @apply px-1 #{!important}
  }

  .multiselect-tag-remove-icon {
      @apply text-lime-800
  }
  </style>
