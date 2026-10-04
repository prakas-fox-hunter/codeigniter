const fs = require('node:fs');
const path = require('node:path');
const target = path.join(__dirname, '../public/assets/swagger');
fs.mkdirSync(target, { recursive: true });
for (const file of ['swagger-ui.css', 'swagger-ui-bundle.js', 'swagger-ui-bundle.js.LICENSE.txt', 'LICENSE']) {
  fs.copyFileSync(path.join(__dirname, '../node_modules/swagger-ui-dist', file), path.join(target, file));
}
