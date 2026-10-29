import assert from "node:assert/strict"
import test from "node:test"
import { createPinia, setActivePinia } from "pinia"
import { announcedUser, useLiveUsers } from "../../resources/js/Stores/active-users.ts"

const fakeEcho = () => {
	const whispers: string[] = []
	const callbacks: Record<string, Function> = {}
	const listenedWhispers: string[] = []
	const channel = {
		here: (callback: Function) => { callbacks.here = callback; return channel },
		joining: (callback: Function) => { callbacks.joining = callback; return channel },
		leaving: (callback: Function) => { callbacks.leaving = callback; return channel },
		error: () => channel,
		listenForWhisper: (event: string, callback: Function) => { listenedWhispers.push(event); callbacks[event] = callback; return channel },
		whisper: (event: string) => { whispers.push(event); return channel },
	}
	return { echo: { join: () => channel, leave: () => null }, whispers, callbacks, listenedWhispers }
}

test("a tab joining the online list asks nobody to answer, whatever the number of colleagues online", () => {
	setActivePinia(createPinia())
	const { echo, whispers, callbacks, listenedWhispers } = fakeEcho()
	globalThis.window = { Echo: echo } as never

	const liveUsers = useLiveUsers()
	liveUsers.subscribe()

	const colleagues = Array.from({ length: 300 }, (_, index) => ({ id: index + 2, alias: `user${index}`, name: `User ${index}` }))
	callbacks.here([{ id: 1, alias: "me", name: "Me" }, ...colleagues])
	colleagues.forEach((colleague) => callbacks.joining(colleague))

	assert.ok(whispers.length <= 1)
	assert.ok(whispers.every((event) => event === "otherIsNavigating"))
	assert.deepEqual(listenedWhispers, ["otherIsNavigating"])
	assert.equal(liveUsers.count, 301)
})

test("what a tab tells every colleague about its user is their name only, never their email, shops or settings", () => {
	assert.deepEqual(
		announcedUser({ id: 7, username: "anna", contact_name: "Anna", email: "anna@example.com", agent_shops: [1, 2], settings: { app_theme: "dark" } } as never),
		{ id: 7, username: "anna", contact_name: "Anna" },
	)
})
