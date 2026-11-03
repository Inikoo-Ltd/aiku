import assert from "node:assert/strict"
import test from "node:test"
import { createRenderer, h, nextTick, reactive } from "vue"
import KeyedMemoRows from "../../resources/js/Components/Table/KeyedMemoRows.ts"

type FakeNode = { tag: string, text: string, children: FakeNode[], parent: FakeNode | null }

const fakeNode = (tag: string, text = ""): FakeNode => ({ tag, text, children: [], parent: null })

const { createApp } = createRenderer<FakeNode, FakeNode>({
	createElement: (tag) => fakeNode(tag),
	createText: (text) => fakeNode("#text", text),
	createComment: (text) => fakeNode("#comment", text),
	setText: (node, text) => { node.text = text },
	setElementText: (node, text) => { node.children = [fakeNode("#text", text)] },
	insert: (child, parent, anchor) => {
		child.parent?.children.splice(child.parent.children.indexOf(child), 1)
		const at = anchor ? parent.children.indexOf(anchor) : -1
		parent.children.splice(at < 0 ? parent.children.length : at, 0, child)
		child.parent = parent
	},
	remove: (child) => {
		child.parent?.children.splice(child.parent.children.indexOf(child), 1)
		child.parent = null
	},
	parentNode: (node) => node.parent,
	nextSibling: (node) => node.parent ? node.parent.children[node.parent.children.indexOf(node) + 1] ?? null : null,
	patchProp: () => {},
})

const mountRows = (rows: { id: number, name: string }[]) => {
	const data = reactive({ rows })
	const draws: number[] = []
	const root = fakeNode("tbody")

	createApp({
		render: () => h(KeyedMemoRows, {
			rows: data.rows,
			rowKey: (item: { id: number }) => `row-${item.id}`,
			memo: (item: { name: string }) => [item.name],
		}, {
			default: ({ item }: { item: { id: number, name: string } }) => {
				draws.push(item.id)

				return [h("tr", item.name)]
			},
		}),
	}).mount(root)

	const shown = () => root.children.filter((node) => node.tag === "tr").map((node) => node.children[0].text)

	return { data, draws, shown }
}

test("taking a row out does not redraw the rows below it", async () => {
	const { data, draws, shown } = mountRows([{ id: 1, name: "A" }, { id: 2, name: "B" }, { id: 3, name: "C" }])
	draws.length = 0

	data.rows.splice(0, 1)
	await nextTick()

	assert.deepEqual(draws, [])
	assert.deepEqual(shown(), ["B", "C"])
})

test("a changed row is the only one redrawn", async () => {
	const { data, draws, shown } = mountRows([{ id: 1, name: "A" }, { id: 2, name: "B" }, { id: 3, name: "C" }])
	draws.length = 0

	data.rows[1].name = "B2"
	await nextTick()

	assert.deepEqual(draws, [2])
	assert.deepEqual(shown(), ["A", "B2", "C"])
})

test("rows reloaded as new objects with the same values are not redrawn, and keep their order", async () => {
	const { data, draws, shown } = mountRows([{ id: 1, name: "A" }, { id: 2, name: "B" }, { id: 3, name: "C" }])
	draws.length = 0

	data.rows = [{ id: 3, name: "C" }, { id: 1, name: "A" }, { id: 4, name: "D" }]
	await nextTick()

	assert.deepEqual(draws, [4])
	assert.deepEqual(shown(), ["C", "A", "D"])
})
