import { ComputedRef, InjectionKey, Ref } from 'vue'

export interface SearchBookmarkApi {
    isAvailable: ComputedRef<boolean>
    isSaving: Ref<boolean>
    isBookmarked: (url: string) => boolean
    toggleBookmark: (item: { label: string; url: string }) => void
}

export const SearchBookmarkKey: InjectionKey<SearchBookmarkApi> = Symbol('searchBookmark')
