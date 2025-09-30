#!/usr/bin/env node
const esbuild = require('esbuild');

const isWatch = process.argv.includes('--watch');

esbuild.build({
  entryPoints: ['assets/js/admin/dashboard.js'],
  bundle: true,
  outfile: 'build/admin-dashboard.js',
  minify: !isWatch,
  sourcemap: isWatch,
  target: ['es2020'],
  platform: 'browser',
}).then(() => {
  if (!isWatch) {
    console.log('Build complete');
  }
}).catch((error) => {
  console.error(error);
  process.exit(1);
});
