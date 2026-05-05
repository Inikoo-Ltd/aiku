<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { inject, computed, ref } from "vue"

const layout = inject("layout", {})
const location = layout?.app?.name || "iris"

const props = withDefaults(
  defineProps<{
    href: any
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

/**
 * Normalize raw input
 */
const computedHref = computed<string | null>(() => {
  const raw = props.canonical_url ?? props.href
  if (typeof raw !== "string" || !raw.trim()) return null

  if (props.type !== "internal") return raw

  try {
    // external → convert to relative
    if (/^https?:\/\//.test(raw)) {
      const parsed = new URL(raw)
      return parsed.pathname + parsed.search + parsed.hash
    }

    // ensure leading slash (except hash)
    if (raw.startsWith("#")) return raw

    return raw.startsWith("/")
      ? raw
      : `/${raw.replace(/^\/+/, "")}`
  } catch {
    return null
  }
})

/**
 * Final href used in template
 * - SSR safe
 * - no window usage
 * - normalize "/#section" → "#section"
 */
const resolvedHref = computed<string | null>(() => {
  const href = computedHref.value
  if (!href) return null

  if (href.startsWith("/#")) {
    return href.slice(1) // "#section"
  }

  return href
})

/**
 * Routing decision only (never for anchors)
 */
const linkLocation = computed<"iris" | "retina" | null>(() => {
  const href = computedHref.value
  if (!href) return null

  // anchors handled by browser
  if (href.startsWith("#") || href.startsWith("/#")) {
    return null
  }

  return href.startsWith("/app") || href.startsWith("/retina")
    ? "retina"
    : "iris"
})

const isAnchor = computed(() => {
  const href = resolvedHref.value
  return !!href && href.startsWith("#")
})

const isLoading = ref(false)

const handleClick = (event: MouseEvent) => {
  if (
    !resolvedHref.value ||
    isAnchor.value ||
    event.defaultPrevented ||
    event.button !== 0 ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey ||
    props.target !== "_self"
  ) {
    return
  }

  isLoading.value = true
  emit("start")
}
</script>

<template>
  <!-- Inertia link (same app, NOT anchor) -->
  <Link
    v-if="
      type === 'internal' &&
      resolvedHref &&
      linkLocation === location &&
      !isAnchor
    "
    :href="resolvedHref"
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

  <!-- Anchor OR external OR cross-app -->
  <a
    v-else-if="resolvedHref"
    :href="resolvedHref"
    :class="class"
    :style="style"
    :target="target"
    rel="noopener noreferrer"
    @click="handleClick"
  >
    <slot :isLoading="isLoading">{{ label }}</slot>
  </a>

  <!-- fallback -->
  <div v-else>
    <slot>{{ label }}</slot>
  </div>
</template>