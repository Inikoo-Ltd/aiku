import assert from "node:assert/strict"
import test from "node:test"
import { announcementsAtPosition } from "../../resources/js/Iris/Composables/useAnnouncementVisibility.ts"

const now = Date.parse("2026-10-01T12:00:00Z")

const announcement = (ulid: string, position: string, authState: "all" | "logged_in" | "logged_out") => ({
	ulid,
	settings: { position, target_users: { auth_state: authState } },
})

test("renders an announcement for every visitor once, without an auth class", () => {
	const forEveryone = announcement("a", "top-bar", "all")

	assert.deepEqual(announcementsAtPosition([forEveryone], "top-bar", now), [{ announcement: forEveryone, audience: "everyone" }])
})

test("renders both auth variants so the html class, not hydration, decides which one shows", () => {
	const loggedIn = announcement("in", "top-bar", "logged_in")
	const loggedOut = announcement("out", "top-bar", "logged_out")

	assert.deepEqual(announcementsAtPosition([loggedIn, loggedOut], "top-bar", now), [
		{ announcement: loggedOut, audience: "logged_out" },
		{ announcement: loggedIn, audience: "logged_in" },
	])
})

test("renders a logged in only announcement hidden for logged out visitors", () => {
	const loggedIn = announcement("in", "top-bar", "logged_in")

	assert.deepEqual(announcementsAtPosition([loggedIn], "top-bar", now), [
		{ announcement: loggedIn, audience: "logged_in" },
	])
})

test("keeps the first match per position and skips other positions and finished schedules", () => {
	const finished = { ...announcement("old", "top-bar", "all"), schedule_finish_at: "2026-09-30T00:00:00Z" }
	const footer = announcement("footer", "top-footer", "all")
	const current = announcement("current", "top-bar", "all")
	const later = announcement("later", "top-bar", "all")

	assert.deepEqual(announcementsAtPosition([finished, footer, current, later], "top-bar", now), [{ announcement: current, audience: "everyone" }])
	assert.deepEqual(announcementsAtPosition([finished], "bottom-menu", now), [])
})
