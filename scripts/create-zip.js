#!/usr/bin/env node
/**
 * This file is part of Moodle - http://moodle.org/
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright 2025 Sebastien Chassande-Barrioz <chassande@gmail.com>
 */

/**
 * Script to create a deployment zip file with a single 'helloasso' folder
 */

const fs = require('fs');
const path = require('path');
const archiver = require('archiver');

// Read package.json version
const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
const npmName = packageJson.name;
const npmVersion = packageJson.version;
const npmZipFolderName = packageJson.zip_folder_name;

// Read Moodle version from version.php
const versionPhp = fs.readFileSync('version.php', 'utf8');
const versionMatch = versionPhp.match(/\$plugin->version\s*=\s*(\d+);/);
const moodleVersion = versionMatch ? versionMatch[1] : 'unknown';

const zipName = `${npmName}-${npmVersion}-${moodleVersion}.zip`;
const zipPath = path.join('..', zipName);

// Files and folders to exclude
const excludeItems = [
    'node_modules',
    'package-lock.json',
    '.git',
    '.gitignore',
    '.vscode',
    '.idea',
    'scripts',
    '.DS_Store',
    'Thumbs.db',
    'Gruntfile.js',
    'package.json',
    'package-lock.json'
];

console.log('\x1b[36m%s\x1b[0m', 'Creating deployment package...');

// Create write stream for zip file
const output = fs.createWriteStream(path.resolve(zipPath));
const archive = archiver('zip', {
    zlib: { level: 9 } // Maximum compression
});

// Listen for events
output.on('close', function() {
    console.log('\x1b[32m%s\x1b[0m', `✓ Package created successfully: ${zipName}`);
    console.log('\x1b[36m%s\x1b[0m', `  Location: ${path.resolve(zipPath)}`);
    console.log('\x1b[36m%s\x1b[0m', `  Size: ${(archive.pointer() / 1024).toFixed(2)} KB`);
    console.log('\x1b[36m%s\x1b[0m', `  Structure: ${npmZipFolderName}/ (ready for deployment)`);
});

archive.on('error', function(err) {
    console.error('\x1b[31m%s\x1b[0m', '✗ Error creating package:');
    console.error(err.message);
    process.exit(1);
});

archive.on('warning', function(err) {
    if (err.code === 'ENOENT') {
        console.warn('\x1b[33m%s\x1b[0m', `Warning: ${err.message}`);
    } else {
        throw err;
    }
});

// Pipe archive to output file
archive.pipe(output);

// Function to check if item should be excluded
function shouldExclude(itemPath) {
    const relativePath = path.relative('.', itemPath);
    const parts = relativePath.split(path.sep);
    
    // Check if any part of the path matches excluded items
    for (const part of parts) {
        if (excludeItems.includes(part)) {
            return true;
        }
    }
    
    return false;
}

// Add files recursively
function addFiles(src, archivePath) {
    const entries = fs.readdirSync(src, { withFileTypes: true });
    
    for (const entry of entries) {
        const srcPath = path.join(src, entry.name);
        const archiveFilePath = path.join(archivePath, entry.name);
        
        // Skip excluded items
        if (shouldExclude(srcPath)) {
            continue;
        }
        
        if (entry.isDirectory()) {
            addFiles(srcPath, archiveFilePath);
        } else {
            archive.file(srcPath, { name: archiveFilePath });
        }
    }
}

console.log('\x1b[36m%s\x1b[0m', `  Adding files to ${npmZipFolderName}/ folder...`);
addFiles('.', npmZipFolderName);

// Finalize the archive
archive.finalize();
