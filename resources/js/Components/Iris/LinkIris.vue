<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { inject, computed } from "vue"

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
    type?: string // "internal" | "external"
    canonical_url?: string
  }>(),
  {
    header: "",
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
  const env = layout?.app?.environment || "local"
  const domainType = layout?.retina?.type || "b2b"
  let url = String(props.canonical_url || props.href)

  // 🔹 If link type is external, just return it as-is
  if (props.type !== "internal") return url

  // 🔹 For internal links, ensure we return only the pathname (e.g. "/dashboard")
  try {
    // If URL is absolute (starts with https:// or http://)
    if (/^https?:\/\//.test(url)) {
      const parsed = new URL(url)
      return parsed.pathname + parsed.search + parsed.hash
    }

    // If URL already relative (starts with "/")
    if (url.startsWith("/")) {
      return url
    }

    // Ensure relative paths always start with a slash
    return "/" + url.replace(/^\/+/, "")
  } catch {
    // Fallback in case of malformed URL
    return url
  }
})

</script>

<template>
  <Link v-if="type == 'internal'" :href="computedHref" :method="props.method"
    :headers="{ is_logged_in: layout?.iris?.is_logged_in, ...props.header}" :as="props.as" :class="props.class"
    :style="props.style" :target="props.target" @start="emit('start')"
    @success="emit('success')"
    @error="emit('error')"
    @finish="emit('finish')">
  <slot>{{ props.label }}</slot>
  </Link>
  <a v-else :href="props.href" :class="props.class" :style="props.style" :target="props.target"
    rel="noopener noreferrer">
    <slot>{{ props.label }}</slot>
  </a>
</template>
