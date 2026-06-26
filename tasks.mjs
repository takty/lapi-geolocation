import { watch as nodeWatch } from 'node:fs';
import { copyFile, mkdir, readdir, readFile } from 'node:fs/promises';
import path from 'node:path';

export async function* walk(dir, suffix) {
	const entries = await readdir(dir, { withFileTypes: true });

	for (const entry of entries) {
		const srcPath = path.join(dir, entry.name);

		if (entry.isDirectory()) {
			yield* walk(srcPath, suffix);
		} else if (entry.isFile() && srcPath.endsWith(suffix)) {
			yield srcPath;
		}
	}
}

export async function filesEqual(a, b) {
	try {
		const [ba, bb] = await Promise.all([
			readFile(a),
			readFile(b)
		]);

		return ba.equals(bb);
	} catch {
		return false;
	}
}

export async function copyFiles(srcDir, dstDir, suffix) {
	let copied = 0;

	for await (const srcPath of walk(srcDir, suffix)) {
		const relPath = path.relative(srcDir, srcPath);
		const dstPath = path.join(dstDir, relPath);

		if (await filesEqual(srcPath, dstPath)) {
			continue;
		}
		await mkdir(path.dirname(dstPath), { recursive: true });
		await copyFile(srcPath, dstPath);

		console.log(`copied: ${srcPath} -> ${dstPath}`);
		copied++;
	}
	if (copied === 0) {
		console.log('no changes');
	}
}

export function preventOverlap(fn) {
	let running = false;
	let pending = false;
	let latestArgs = [];
	let latestThis = null;

	return async function (...args) {
		latestArgs = args;
		latestThis = this;
		pending = true;

		if (running) return;

		running = true;

		try {
			while (pending) {
				pending = false;
				await fn.apply(latestThis, latestArgs);
			}
		} finally {
			running = false;
		}
	};
}

export function watch(srcDir, suffix, fn) {
	const fnSafely = preventOverlap(fn);

	nodeWatch(srcDir, { recursive: true }, (_eventType, filename) => {
		if (!filename || filename.endsWith(suffix)) {
			fnSafely();
		}
	});
}
