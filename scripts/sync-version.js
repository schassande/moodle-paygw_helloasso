#!/usr/bin/env node
// This file is part of Moodle - http://moodle.org/
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
// 
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// 
// @copyright 2025 Sebastien Chassande-Barrioz <chassande@gmail.com>


/**
 * Script to synchronize version from package.json to version.php
 */

const fs = require('fs');
const path = require('path');

// Read package.json version
const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
const npmVersion = packageJson.version;
const buildNumber = packageJson.build;

// Read version.php content
const versionPhpPath = path.join(__dirname, '..', 'version.php');
let content = fs.readFileSync(versionPhpPath, 'utf8');

// Update version (date-based)
content = content.replace(
    /(\$plugin->version = )\d+;/,
    `$1${buildNumber};`
);

// Update release (from package.json)
content = content.replace(
    /(\$plugin->release = ')[^']+';/,
    `$1${npmVersion} (stable)';`
);

// Write back to version.php
fs.writeFileSync(versionPhpPath, content, 'utf8');

console.log('\x1b[32m%s\x1b[0m', 'Version synchronized successfully!');
console.log('\x1b[36m%s\x1b[0m', `  Moodle version: ${buildNumber}`);
console.log('\x1b[36m%s\x1b[0m', `  Release: ${npmVersion} (stable)`);
