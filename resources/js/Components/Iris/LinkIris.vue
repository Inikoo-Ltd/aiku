<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { inject, computed, ref } from "vue"

type RouteType = string | Record<string, any>

const layout = inject("layout", {})
const location = layout?.app?.name || "iris"

const props = withDefaults(
  defineProps<{
    href: RouteType
    header?: Record<string, any>
    method?: string
    as?: string
    class?: string
    style?: Record<string, any>
    label?: string
    target?: string
    type?: "internal" | "external"
    canonical_url?: string
    id?: number | string
  }>(),
  {
    header: () => ({}),
    method: "get",
    as: "a",
    class: "",
    style: () => ({}),
    label: "",
    target: "_self",
    type: "internal",
  }
)

const emit = defineEmits<{
  (e: "start"): void
  (e: "success"): void
  (e: "error"): void
  (e: "finish"): void
}>()


const computedHref = computed(() => {
  let url = String(props.canonical_url || props.href)

  if (props.type !== "internal") return url

  try {
    if (/^https?:\/\//.test(url)) {
      const parsed = new URL(url)
      return parsed.pathname + parsed.search + parsed.hash
    }

    if (url.startsWith("/")) return url
    return "/" + url.replace(/^\/+/, "")
  } catch {
    return url
  }
})


const getLinkLocation = (link: string): "iris" | "retina" => {
  if (link.startsWith("/app") || link.startsWith("/retina")) {
    return "retina"
  }
  return "iris"
}

const isLoading = ref(false)
</script>

<template>
  <!-- Internal Inertia link (same app) -->
  <Link
    v-if="type === 'internal' && (location === getLinkLocation(computedHref)) && computedHref"
    :href="computedHref"
    :method="method"
    :headers="{ is_logged_in: layout?.iris?.is_logged_in, ...header }"
    :as="as"
    :class="class"
    :style="style"
    :target="target"
    :id="id"
    @start="emit('start'), isLoading = true"
    @success="emit('success')"
    @error="emit('error')"
    @finish="emit('finish'), isLoading = false"
  >
    <slot :isLoading="isLoading">{{ label }}</slot>
  </Link>

  <!-- External or cross-app link -->
  <a
    v-else-if="computedHref"
    :href="computedHref"
    :class="class"
    :style="style"
    :target="target"
    rel="noopener noreferrer"
  >
    <slot>{{ label }}</slot>
  </a>

  <div v-else><slot>{{ label }}</slot></div>
</template>
