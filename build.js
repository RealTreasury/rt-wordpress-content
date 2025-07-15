const fs = require('fs');
const path = require('path');
const ejs = require('ejs');

const templatesDir = path.join(__dirname, 'templates');

const homepageStats = [
  { number: '4-6 Weeks', label: 'Average Selection Time' },
  { number: '100+', label: 'Vendors Evaluated' },
  { number: '100%', label: 'Independent & Unbiased' },
  { number: '45+', label: 'Years Experience' }
];

function ensureDir(dir) {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

function* walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const res = path.resolve(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === 'partials') continue;
      yield* walk(res);
    } else if (entry.isFile() && res.endsWith('.html')) {
      yield res;
    }
  }
}

for (const file of walk(templatesDir)) {
  const rel = path.relative(templatesDir, file);
  const dest = path.join(__dirname, rel);
  const template = fs.readFileSync(file, 'utf8');
  const html = ejs.render(template, { homepageStats }, { filename: file, root: templatesDir });
  ensureDir(path.dirname(dest));
  fs.writeFileSync(dest, html);
  console.log('Rendered', dest);
}
