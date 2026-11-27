import { computed, type CSSProperties } from "vue"
import { usePage } from "@inertiajs/vue3"

export type ChatWallpaper = { pattern: string | null; colour: string | null }

export type ChatWallpaperCatalogue = {
    patterns: { key: string; label: string; is_seasonal: boolean; is_new: boolean; users: number }[]
    colours: { key: string; hex: string }[]
    stored: { pattern: string; colour: string; mode: string; style?: string }
}

const tiles = import.meta.glob("./wallpapers/*.svg", { eager: true, query: "?url", import: "default" }) as Record<string, string>

export const tileUrl = (pattern: string | null | undefined) => (pattern ? tiles[`./wallpapers/${pattern}.svg`] ?? null : null)

export const hasWallpaper = (wallpaper: ChatWallpaper | null | undefined) => !!(wallpaper?.pattern || wallpaper?.colour)

export const wallpaperStyle = (wallpaper: ChatWallpaper | null | undefined): CSSProperties => {
    if (!hasWallpaper(wallpaper)) return {}
    const url = tileUrl(wallpaper!.pattern)
    return {
        backgroundColor: wallpaper!.colour ?? "#ffffff",
        ...(url ? { backgroundImage: `url("${url}")`, backgroundRepeat: "repeat" } : {}),
    }
}

export const listSwatch = (conversation: { wallpaper?: ChatWallpaper | null; wallpaper_is_set?: boolean }, mode: string | undefined) =>
    conversation.wallpaper?.colour && (conversation.wallpaper_is_set || (mode && mode !== "same")) ? conversation.wallpaper.colour : null

export const useBubbles = () => computed(() => (usePage().props as any)?.auth?.user?.settings?.chat_wallpapers?.stored?.style === "bubbles")
