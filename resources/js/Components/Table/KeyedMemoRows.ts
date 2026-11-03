import { defineComponent, Fragment, h, type PropType, type VNode } from "vue"

/**
 * v-memo on a v-for caches each row by its position, so taking one row out of the list re-renders
 * every row below it (INI-176: packing a row off the Pending tab redrew half the delivery note).
 * This keeps each row's vnode by its key instead: a row is drawn again only when its memo values
 * change, wherever it moved to. Returning the same vnode is what lets Vue skip it, as v-memo does.
 */
export default defineComponent({
    name: "KeyedMemoRows",
    props: {
        rows: { type: Array as PropType<Record<string, any>[]>, required: true },
        rowKey: { type: Function as PropType<(item: Record<string, any>) => string>, required: true },
        memo: { type: Function as PropType<(item: Record<string, any>, index: number) => unknown[]>, required: true },
    },
    setup(props, { slots }) {
        let cache = new Map<string, VNode>()

        return () => {
            const next = new Map<string, VNode>()
            const vnodes = props.rows.map((item, index) => {
                const key = props.rowKey(item)
                const memo = props.memo(item, index)
                const cached = cache.get(key)
                // Vue copies a vnode that is already mounted unless it carries its memo, as v-memo's do
                const vnode = cached?.el && !next.has(key) && cached.memo?.length === memo.length && cached.memo.every((value, i) => Object.is(value, memo[i]))
                    ? cached
                    : slots.default!({ item, index })[0]
                vnode.key = key
                vnode.memo = memo
                next.set(key, vnode)

                return vnode
            })
            cache = next

            return h(Fragment, vnodes)
        }
    },
})
