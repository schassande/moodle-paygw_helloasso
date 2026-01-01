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
 * Gruntfile for paygw_helloasso plugin
 * 
 * This file configures Grunt to compile AMD modules from src to build directory.
 */

/* eslint-env node */

module.exports = function(grunt) {
    'use strict';

    // Project configuration
    grunt.initConfig({
        uglify: {
            amd: {
                files: [{
                    expand: true,
                    cwd: 'amd/src',
                    src: ['*.js'],
                    dest: 'amd/build',
                    ext: '.min.js',
                    extDot: 'last'
                }],
                options: {
                    compress: {
                        drop_console: false
                    },
                    mangle: true,
                    sourceMap: true,
                    sourceMapIncludeSources: true
                }
            }
        },

        watch: {
            amd: {
                files: ['amd/src/*.js'],
                tasks: ['uglify:amd']
            }
        }
    });

    // Load NPM tasks
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');

    // Register tasks
    grunt.registerTask('amd', ['uglify:amd']);
    grunt.registerTask('default', ['amd']);
};
