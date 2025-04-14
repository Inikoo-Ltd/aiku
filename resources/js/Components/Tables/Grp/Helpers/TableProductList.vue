<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue";
import { onMounted, onUnmounted, ref } from "vue";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import InputNumber from "primevue/inputnumber";
import InputText from "primevue/inputtext";
import InputGroup from "primevue/inputgroup";
import InputGroupAddon from "primevue/inputgroupaddon";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { routeType } from "@/types/route";
import axios from "axios";
import { debounce } from "lodash-es";
import { useForm } from "@inertiajs/vue3";
import { faCloud, faCompress, faExpandArrowsAlt, faSearch, faSpinner } from "@fal";
import { faMinus, faPlus, faSave, faUndo } from "@fas";

import { notify } from "@kyvg/vue3-notification";
import { trans } from "laravel-vue-i18n";
import QuantityInput from "@/Components/Utils/QuantityInput.vue"

library.add(
  faSearch,
  faPlus,
  faMinus,
  faSpinner,
  faCloud,
  faUndo,
  faExpandArrowsAlt,
  faSave,
  faCompress
);

const props = defineProps<{
  fetchRoute: routeType;
  data: any;
  action: any;
  modalOpen: boolean;
  tab: string | Number;
}>();

const emits = defineEmits<{
  (e: "optionsList", value: any[]): void;
  (e: "update:tab", value: string): void;
}>();

const products = ref<any[]>([]);
const optionsMeta = ref(null);
const optionsLinks = ref(null);
const isLoading = ref(false);
const searchQuery = ref("");
const iconStates = ref<Record<number, { increment: string; decrement: string }>>({});
const addedProductIds = ref(new Set<number>());
const currentTab = ref(props.tab);
const isModalUploadOpen = ref(props.modalOpen);

const isProductAdded = (id: number): boolean => {
  return addedProductIds.value.has(id);
};

const onClickProduct = async (tabSlug: string) => {
  if (tabSlug === currentTab.value) return;
  emits("update:tab", tabSlug);
  isModalUploadOpen.value = true;
  console.log(isModalUploadOpen.value);
};

const resetIcons = (id: number) => {
  const product = products.value.find((product) => product.id === id);

  if (product) {
    product.inputTriggered = false;
  }

  iconStates.value[id] = {
    increment: "fal fa-plus",
    decrement: "fal fa-minus",
  };
};

const onUndoClick = (id: number) => {
  resetIcons(id);
};

const onManualInputChange = (value: number, slotProps: any) => {
  slotProps.data.quantity_ordered = value;

  slotProps.data.inputTriggered = true;
  iconStates.value[slotProps.data.id] = {
    increment: "fal fa-cloud",
    decrement: "fal fa-undo",
  };
};

const resetProducts = () => {
  products.value = [];
  optionsMeta.value = null;
  optionsLinks.value = null;
};

const getUrlFetch = (additionalParams: {}) => {
  return route(props.fetchRoute.name, {
    ...props.fetchRoute.parameters,
    ...additionalParams,
  });
};

const fetchProductList = async (url?: string) => {
  isLoading.value = true;
  const urlToFetch = url || route(props.fetchRoute.name, props.fetchRoute.parameters);
  console.log(urlToFetch, "heheh");

  try {
    const response = await axios.get(urlToFetch);
    const data = response.data;

    if (url && optionsLinks.value?.next) {
      products.value = data.data;
    } else {
      resetProducts();
      products.value = data.data;
    }

    optionsMeta.value = data.meta;
    optionsLinks.value = data.links;
    console.log(products, "hhohoho");

    if (!addedProductIds.value) {
      addedProductIds.value = new Set();
    }
   /*  data.data.forEach((product: any) => {
      if (product.purchase_order_id) {
        addedProductIds.value.add(product.purchase_order_id);
      }
    }); */

    /* emits("optionsList", products.value); */
  } catch (error) {
    console.error("Error fetching product list:", error);
  } finally {
    isLoading.value = false;
  }
};

// Debounced fetch function for search
const debouncedFetch = debounce((query: string) => {
  fetchProductList(getUrlFetch({ "filter[global]": query }));
}, 500);

// Search handler
const onSearchQuery = (query: string) => {
  console.log(query, "Search query updated");
  debouncedFetch(query);
};

const formProducts = useForm({
  quantity_ordered: 0,
});

// Submit handler for adding products
const onSubmitAddProducts = async (data: any, slotProps: any) => {
  const productId = slotProps.data.purchase_order_id;
  console.log("Decrement:", slotProps.data.quantity_ordered);

  try {
    if (slotProps.data.quantity_ordered > 0) {
      // Handle update or add
      if (addedProductIds.value && addedProductIds.value.has(productId)) {
        // Update product
        if (slotProps.data.purchase_order_id) {
          await formProducts
            .transform(() => ({
              quantity_ordered: slotProps.data.quantity_ordered,
            }))
            .patch(
              route(slotProps?.data?.updateRoute?.name || "#", {
                ...slotProps.data.updateRoute?.parameters,
              })
            );
        }
      } else {
        // Add product
        await formProducts
          .transform(() => ({
            quantity_ordered: slotProps.data.quantity_ordered,
          }))
          .post(
            route(data.route?.name || "#", {
              ...data.route?.parameters,
              historicAsset: slotProps.data.historic_id,
              orgStock: slotProps.data.org_stock_id,
            })
          );

        // Refresh list and update addedProductIds
        /* await fetchProductList(); */
        
        addedProductIds.value.add(productId);
        iconStates.value[productId] = {
          increment: "fal fa-cloud",
          decrement: "fal fa-undo",
        };
      }

      // Notify success
      notify({
        title: trans("Success!"),
        text: trans("Product successfully added or updated."),
        type: "success",
      });
    } else if (slotProps.data.quantity_ordered === 0) {
      // Handle delete
      if (addedProductIds.value && addedProductIds.value.has(productId)) {
        await formProducts.delete(
          route(slotProps?.data?.deleteRoute?.name || "#", {
            ...slotProps.data.deleteRoute?.parameters,
          })
        );

        // Remove product ID from the addedProductIds set
        addedProductIds.value.delete(productId);

        // Refresh list to reflect changes
        await fetchProductList();

        // Notify success
        notify({
          title: trans("Success!"),
          text: trans("Product successfully deleted."),
          type: "success",
        });
      }
    }
  } catch (error) {
    console.error("Error adding/updating/deleting product:", error);

    // Notify error
    notify({
      title: trans("Something went wrong"),
      text: trans("An error occurred while processing the product."),
      type: "error",
    });
  }
};

const onFetchNext = async () => {
  if (optionsLinks.value?.next && !isLoading.value) {
    await fetchProductList(optionsLinks.value.next);
  }
};

const onKeyDown = (slotProps: any) => {
  console.log(slotProps, "we ap ni");
  if (!slotProps.data.inputTriggered) {
    slotProps.data.inputTriggered = true;
    iconStates.value[slotProps.data.id] = {
      increment: "fal fa-cloud",
      decrement: "fal fa-undo",
    };
  }
};

const onValueChange = (slotProps: any) => {
  slotProps.data.quantity_ordered = parseFloat(slotProps.data.quantity_ordered || 0);
};

onMounted(async () => {
  const tableBody = document.querySelector(".p-datatable-scrollable-body");
  if (tableBody) {
    tableBody.addEventListener("scroll", debounce(onFetchNext, 200));
  }

  isLoading.value = true;
  try {
    await fetchProductList();
  } catch (error) {
    console.error("Error during initial product fetch:", error);
  } finally {
    isLoading.value = false;
  }
});

onUnmounted(() => {
  const tableBody = document.querySelector(".p-datatable-scrollable-body");
  if (tableBody) {
    tableBody.removeEventListener("scroll", onFetchNext);
  }
});
</script>

<template>
  <div class="flex-grow">
    <DataTable :value="products" scrollable :loading="isLoading">
      <template #header>
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <FontAwesomeIcon
              icon="fal fa-compress"
              @click="onClickProduct('transactions')"
              v-tooltip="'minimize'"
              class="text-gray-500 hover:text-gray-700 text-lg cursor-pointer" />
          </div>

          <div class="flex items-center gap-2">
            <IconField>
              <InputIcon>
                <FontAwesomeIcon
                  icon="fal fa-search"
                  class="text-gray-500"
                  fixed-width
                  aria-hidden="true" />
              </InputIcon>
              <InputText
                v-model="searchQuery"
                placeholder="Search products"
                @input="onSearchQuery(searchQuery)"
                class="border border-gray-300 rounded-lg px-4 py-2 text-sm" />
            </IconField>
          </div>
        </div>
      </template>

      <template #empty> No Product found. </template>

      <!-- Loading Icon -->
      <template #loading>
        <div>
          <FontAwesomeIcon icon="fal fa-spinner" class="text-2xl animate-spin mb-2" />
          <span>Loading Products...</span>
        </div>
      </template>

      <Column header="Image">
        <template #body="slotProps">
          <img
            :src="`https://primefaces.org/cdn/primevue/images/product/${slotProps.data.image}`"
            :alt="slotProps.data.image"
            class="w-24 rounded" />
        </template>
      </Column>
      <Column field="code" header="Code"></Column>
      <Column field="name" header="Description"></Column>
      <Column header="Action" style="width: 8%">
        <template #body="slotProps">
          <QuantityInput
            :data="slotProps.data"
            :action="action"
            @update="onKeyDown(slotProps)"
            @submit="onSubmitAddProducts(action, slotProps)"
            @undo="onUndoClick" />
        </template>
      </Column>

      <template #footer>
        <div class="text-center">
          In total there are
          {{ products ? products.length : 0 }} products.
        </div>
      </template>
    </DataTable>
  </div>
</template>

<style scoped>
.p-datatable .p-datatable-loading-overlay {
  background: transparent !important;
  box-shadow: none !important;
}

.p-datatable .p-datatable-loading-overlay .p-datatable-loading {
  background: none !important;
  border: none !important;
  box-shadow: none !important;
}

.custom-input-number :deep(.p-inputnumber) {
  --p-inputnumber-button-width: 35px;
  height: 35px;
}

.custom-button {
  width: var(--p-inputnumber-button-width, 35px);
  height: var(--p-inputnumber-button-width, 35px);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  background-color: #f5f5f5;
  border-radius: 4px;
}

/* InputNumber customization */
.custom-input-number :deep(.p-inputnumber) {
  --p-inputnumber-button-width: 35px;
  height: 35px;
}

/* Optional: Hover effect for buttons */
.custom-button:hover {
  background-color: #e0e0e0;
}

.animate-spin {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
</style>
