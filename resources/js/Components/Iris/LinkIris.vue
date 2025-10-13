<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { inject } from "vue"


type RouteType = string | Record<string, any>
const layout = inject("layout", {})
const props = withDefaults(
  defineProps<{
    href: RouteType
    header?: string
    method?: string
    as?: string
    class?: string
    style?: Record<string, any>
    label?: string
    target?: string
  }>(),
  {
    header: "",
    method: "get",
    as: "a",
    class: "",
    style: () => ({}),
    label: "",
    target: "_self",
  }
)
</script>

<template>
  <!-- <Link
    v-if="layout?.app?.environment === 'production'"
    :href="props.href"
    :method="props.method"
    :headers="props.header"
    :as="props.as"
    :class="props.class"
    :style="props.style"
    :target="props.target"
  >
    <slot>
        {{ props.label }}
    </slot>
  </Link> -->
  <a
    :href="props.href"
    :class="props.class"
    :headers="props.header"
    :style="props.style"
    :target="props.target"
    rel="noopener noreferrer"
  >
   <slot>
        {{ props.label }}
    </slot>
  </a>
</template>
