import { spawn } from "node:child_process"
import fs from "node:fs"
import os from "node:os"

const TARGETS = [
	{ name: "grp", script: "build.grp", heapMb: 8192, peakMemoryMb: 7200 },
	{ name: "retina", script: "build.retina", heapMb: 6144, peakMemoryMb: 4100 },
	{ name: "iris", script: "build.iris", heapMb: 4096, peakMemoryMb: 2700 },
	{ name: "pupil", script: "build.pupil", heapMb: 4096, peakMemoryMb: 2200 },
	{ name: "aiku-public", script: "build.aiku-public", heapMb: 4096, peakMemoryMb: 1900 },
]

const MEMORY_RESERVE_MB = 1536

const readAvailableMemoryMb = () => {
	try {
		const available = fs
			.readFileSync("/proc/meminfo", "utf8")
			.match(/^MemAvailable:\s+(\d+) kB$/m)

		if (available) {
			return Math.floor(Number(available[1]) / 1024)
		}
	} catch {
		/* not linux */
	}

	return Math.floor(os.freemem() / 1048576)
}

const positiveNumberFromEnv = (name) => {
	const value = Number(process.env[name])

	return Number.isFinite(value) && value > 0 ? value : null
}

const heaviestPeakMemoryMb = Math.max(...TARGETS.map((target) => target.peakMemoryMb))

const memoryBudgetMb = Math.max(
	positiveNumberFromEnv("BUILD_MEMORY_MB") ?? readAvailableMemoryMb() - MEMORY_RESERVE_MB,
	heaviestPeakMemoryMb
)

const maxParallel = Math.max(
	1,
	Math.min(
		TARGETS.length,
		positiveNumberFromEnv("BUILD_PARALLEL") ?? Math.max(1, os.cpus().length - 1)
	)
)

const startBuild = (target) => {
	const startedAt = Date.now()
	const output = []
	const child = spawn("npm", ["run", target.script], {
		env: { ...process.env, NODE_OPTIONS: `--max-old-space-size=${target.heapMb}` },
		stdio: ["ignore", "pipe", "pipe"],
	})

	child.stdout.on("data", (chunk) => output.push(chunk))
	child.stderr.on("data", (chunk) => output.push(chunk))

	return new Promise((resolve) => {
		child.on("close", (code) =>
			resolve({
				target,
				code,
				seconds: (Date.now() - startedAt) / 1000,
				output: Buffer.concat(output).toString(),
			})
		)
	})
}

const queued = [...TARGETS]
const running = new Set()
const finished = []
let reservedMemoryMb = 0
let aborted = false

const hasRoomFor = (target) =>
	running.size < maxParallel && reservedMemoryMb + target.peakMemoryMb <= memoryBudgetMb

const fillLanes = () => {
	if (aborted) {
		return
	}

	for (
		let index = queued.findIndex(hasRoomFor);
		index !== -1;
		index = queued.findIndex(hasRoomFor)
	) {
		const [target] = queued.splice(index, 1)

		reservedMemoryMb += target.peakMemoryMb

		const job = startBuild(target).then((result) => ({ ...result, job }))

		running.add(job)
		console.log(
			`▶ ${target.name} started (~${target.peakMemoryMb}MB, ` +
				`${reservedMemoryMb}/${memoryBudgetMb}MB reserved, ${running.size} running)`
		)
	}
}

const startedAt = Date.now()

console.log(
	`Build plan: memory budget ${memoryBudgetMb}MB, up to ${maxParallel} builds in parallel ` +
		`(${os.cpus().length} cpus, ${readAvailableMemoryMb()}MB available)`
)

fillLanes()

while (running.size > 0) {
	const result = await Promise.race(running)

	running.delete(result.job)
	reservedMemoryMb -= result.target.peakMemoryMb
	finished.push(result)

	process.stdout.write(`\n──── ${result.target.name} ────\n${result.output}`)
	console.log(
		`${result.code === 0 ? "✔" : "✖"} ${result.target.name} ` +
			`${result.code === 0 ? "built" : `failed (exit ${result.code})`} in ${result.seconds.toFixed(1)}s`
	)

	if (result.code !== 0) {
		aborted = true
	}

	fillLanes()
}

const failures = finished.filter((result) => result.code !== 0)

console.log(`\nFrontend build finished in ${((Date.now() - startedAt) / 1000).toFixed(1)}s`)

for (const result of finished) {
	console.log(
		`  ${result.code === 0 ? "✔" : "✖"} ${result.target.name} ${result.seconds.toFixed(1)}s`
	)
}

if (queued.length > 0) {
	console.log(`  ⨯ skipped: ${queued.map((target) => target.name).join(", ")}`)
}

if (failures.length > 0) {
	process.exit(1)
}
