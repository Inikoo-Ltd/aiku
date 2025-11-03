<script setup lang="ts">
import { ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import Dialog from "primevue/dialog";
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import SelectQuery from "@/Components/SelectQuery.vue";

const props = defineProps<{
  show: boolean;
  attribut?: {
    href: string;
    type: string;
    id: string;
    workshop: string;
    target: string;
  };
}>();

const emit = defineEmits(["close", "update"]);

function getRoute() {
  if (route().current().includes("fulfilments") || route().params["fulfilment"]) {
    return route("grp.org.fulfilments.show.web.webpages.index", {
      organisation: route().params["organisation"],
      fulfilment: route().params["fulfilment"],
      website: route().params["website"],
    });
  } else if (route().current().includes("shop") || route().params["shop"]) {
    return route("grp.org.shops.show.web.webpages.index", {
      organisation: route().params["organisation"],
      shop: route().params["shop"],
      website: route().params["website"],
    });
  } else {
    return route("grp.org.shops.show.web.webpages.index", {
      organisation: route().params["organisation"],
      shop: route().params["shop"],
      website: route().params["website"],
    });
  }
}

const form = useForm({
  href: props.attribut?.href || "",
  type: props.attribut?.type || "internal",
  workshop: null,
  id: null,
  target: props.attribut?.target || "_self",
});

watch(
  () => props.attribut,
  (newValue) => {
    if (newValue) {
      form.href = newValue.href || "";
      form.type = newValue.type || "internal";
      form.workshop = newValue.workshop || null;
      form.id = newValue.id || null;
      form.target = newValue.target || "_self";
    }
  },
  { immediate: true }
);

function closeDialog() {
  emit("close");
}

function update() {
  emit("update", form.data());
  emit("close");
}

const onChangeLink = (value) => {
  form.href = value.href;
  form.id = value.id;
  form.workshop = value.workshop;
};

const options = [
  { label: "Internal", value: "internal" },
  { label: "External", value: "external" },
];

const target = [
  { label: "In this page", value: "_self" },
  { label: "New Page", value: "_blank" },
];
</script>

<template>
  <Dialog
    v-model:visible="props.show"
    modal
    header="Link Setting"
    :closable="true"
    class="w-full sm:w-[500px]"
    @hide="closeDialog"
    :contentStyle="{overflowY : 'visible'}"
  >
    <div class="flex flex-col space-y-4">
      <!-- Type -->
      <div>
        <div class="select-none text-sm text-gray-600 mb-2">Type</div>
        <div class="flex space-x-4">
          <label
            v-for="option in options"
            :key="option.value"
            class="flex items-center space-x-2"
          >
            <input type="radio" :value="option.value" v-model="form.type" class="form-radio" />
            <span>{{ option.label }}</span>
          </label>
        </div>
      </div>

      <!-- Target -->
      <div>
        <div class="select-none text-sm text-gray-600 mb-2">Target</div>
        <div class="flex space-x-4">
          <label
            v-for="option in target"
            :key="option.value"
            class="flex items-center space-x-2"
          >
            <input type="radio" :value="option.value" v-model="form.target" class="form-radio" />
            <span>{{ option.label }}</span>
          </label>
        </div>
      </div>

      <!-- Internal Link -->
      <div v-if="form.type === 'internal'">
        <div class="select-none text-sm text-gray-600 mb-2">Link</div>
        <SelectQuery
          fieldName="id"
          :object="true"
          :urlRoute="getRoute()"
          :value="form"
          :closeOnSelect="true"
          label="path"
          :onChange="onChangeLink"
        />
      </div>

      <!-- External Link -->
      <div v-if="form.type === 'external'">
        <div class="select-none text-sm text-gray-600 mb-2">Link</div>
        <PureInput v-model="form.href" />
      </div>

      <!-- Buttons -->
      <div class="flex justify-end space-x-3 pt-3">
        <Button type="white" label="Cancel" @click="closeDialog" />
        <Button type="black" label="Apply" @click="update" />
      </div>
    </div>
  </Dialog>
</template>


