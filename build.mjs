import { copyFiles, watch } from './tasks.mjs';

const SRC_DIR = 'src';
const DST_DIR = 'dist';
const SUFFIX  = '.php';

async function build() {
	try {
		await copyFiles(SRC_DIR, DST_DIR, SUFFIX);
	} catch (err) {
		console.error(err);
	}
}

await build();

if (process.argv.includes('--watch')) {
	console.log(`watching: ${SRC_DIR}/**/*${SUFFIX}`);
	watch(SRC_DIR, SUFFIX, build);
}
