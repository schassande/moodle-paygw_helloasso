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
