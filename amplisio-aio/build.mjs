import { build } from 'esbuild';
import { promises as fs } from 'fs';
import path from 'path';
import crypto from 'crypto';

const args = process.argv.slice(2);
const isWatch = args.includes('--watch');
const isMinify = args.includes('--minify');

const rootDir = process.cwd();
const assetsDir = path.join(rootDir, 'assets');

const entrypoints = [
  { name: 'admin', entry: path.join(rootDir, 'resources/admin/index.js') },
  { name: 'front', entry: path.join(rootDir, 'resources/front/index.js') }
];

const buildEntry = async (entry) => {
  const result = await build({
    entryPoints: [entry.entry],
    bundle: true,
    format: 'iife',
    target: ['es2019'],
    sourcemap: !isMinify,
    minify: isMinify,
    write: false,
    loader: {
      '.css': 'css'
    }
  });

  const jsOutput = result.outputFiles.find((file) => file.path.endsWith('.js'));
  const cssOutput = result.outputFiles.find((file) => file.path.endsWith('.css'));

  const hash = crypto.createHash('sha256').update(jsOutput.contents).digest('hex').slice(0, 8);
  const fileBase = `${entry.name}.bundle-${hash}`;
  const entryDir = path.join(assetsDir, entry.name);

  await fs.mkdir(entryDir, { recursive: true });

  const existingFiles = await fs.readdir(entryDir);
  await Promise.all(
    existingFiles
      .filter((file) => file.startsWith(`${entry.name}.bundle-`))
      .map((file) => fs.unlink(path.join(entryDir, file)))
  ).catch(() => undefined);

  const manifestEntry = {};

  if (jsOutput) {
    const jsPath = path.join(entryDir, `${fileBase}.js`);
    await fs.writeFile(jsPath, jsOutput.contents);
    manifestEntry.js = `${entry.name}/${path.basename(jsPath)}`;
  }

  if (cssOutput) {
    const cssPath = path.join(entryDir, `${fileBase}.css`);
    await fs.writeFile(cssPath, cssOutput.contents);
    manifestEntry.css = `${entry.name}/${path.basename(cssPath)}`;
  }

  return { name: entry.name, manifestEntry };
};

const writeManifest = async (entries) => {
  const manifestPath = path.join(assetsDir, 'manifest.json');
  const manifest = entries.reduce((carry, item) => {
    carry[item.name] = item.manifestEntry;
    return carry;
  }, {});

  await fs.writeFile(manifestPath, JSON.stringify(manifest, null, 2));
};

const run = async () => {
  const outputs = [];
  for (const entry of entrypoints) {
    outputs.push(await buildEntry(entry));
  }
  await writeManifest(outputs);
};

if (isWatch) {
  const chokidar = await import('chokidar');
  const watcher = chokidar.watch(path.join(rootDir, 'resources'));

  await run();

  watcher.on('change', async () => {
    try {
      await run();
      console.log('Rebuilt assets');
    } catch (error) {
      console.error(error);
    }
  });
} else {
  run().catch((error) => {
    console.error(error);
    process.exitCode = 1;
  });
}
