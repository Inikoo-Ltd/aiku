import assert from "node:assert/strict"
import test from "node:test"
import { bannerVideo } from "../../resources/js/Composables/bannerVideo.ts"

const youtube = "https://www.youtube.com/embed/abc123?autoplay=1&mute=1&loop=1&playlist=abc123&controls=0&playsinline=1"

test("every way staff copy a YouTube link plays the same muted loop", () => {
	for (const url of [
		"https://www.youtube.com/watch?v=abc123&t=10s",
		"https://youtu.be/abc123?si=xyz",
		"https://www.youtube.com/embed/abc123",
		"https://youtube.com/shorts/abc123",
		"https://m.youtube.com/watch?v=abc123",
	]) {
		assert.deepEqual(bannerVideo(url), { kind: "iframe", src: youtube }, url)
	}
})

test("vimeo links, including the manage page and unlisted links, play as a background video", () => {
	assert.deepEqual(bannerVideo("https://vimeo.com/manage/videos/1245756584"), { kind: "iframe", src: "https://player.vimeo.com/video/1245756584?background=1" })
	assert.deepEqual(bannerVideo("https://vimeo.com/1245756584"), { kind: "iframe", src: "https://player.vimeo.com/video/1245756584?background=1" })
	assert.deepEqual(bannerVideo("https://vimeo.com/1245756584/a1b2c3d4e5"), { kind: "iframe", src: "https://player.vimeo.com/video/1245756584?h=a1b2c3d4e5&background=1" })
	assert.deepEqual(bannerVideo("https://player.vimeo.com/video/1245756584?h=a1b2c3d4e5"), { kind: "iframe", src: "https://player.vimeo.com/video/1245756584?h=a1b2c3d4e5&background=1" })
})

test("a direct file plays in a video tag even with a query string, anything else shows nothing", () => {
	assert.deepEqual(bannerVideo("https://cdn.example.com/xmas.mp4?v=2"), { kind: "file", src: "https://cdn.example.com/xmas.mp4?v=2" })
	assert.equal(bannerVideo("https://drive.google.com/file/d/abc/view"), null)
	assert.equal(bannerVideo("not a url"), null)
	assert.equal(bannerVideo(""), null)
})
