'use strict';

const // Load gulp plugins and assigning them semantic names.
  gulp = require('gulp'),
  log = require('fancy-log'),
  shell = require('gulp-shell'), // Command line interface for gulp
  // CSS related plugins
  sass = require('gulp-sass'), // Gulp plugin for Sass compilation
  sassglob = require('gulp-sass-glob'), // Glob Sass imports
  sasslint = require('gulp-sass-lint'), // Sass linting
  postcss = require("gulp-postcss"), // pipe css through several plugins (see postcss.config.js) and parse once
  cssnano = require('cssnano'), // Minify css
  // JS related plugins
  eslint = require('gulp-eslint'), // JS linting
  // Utility related plugins
  uglify = require('gulp-uglify-es').default, // Minify Javascript with UglifyJS3
  sourcemaps = require('gulp-sourcemaps'), // Maps code in a compressed file back to it's original position in a source file
  notify = require('gulp-notify'), // Sends message notification via OS
  concat = require('gulp-concat'), // Concatenates files together
  browsersync = require('browser-sync').create(),
  reload = browsersync.reload;

const config = require('./manifest.json');

const paths = {
  sassSrc: ['sass/**/*.scss'], 
  sassDest: 'css',
  jsSrc: ['js/source/*.js', 'node_modules/vanilla-autofill-event/src/autofill-event.js'],
  jsDest: 'js/build',
  template: 'templates/**/*.twig',
  imgSrc: 'images/source/**/*.{png,jpg,gif}',
  imgDest: 'images/optimized',
  svgSrc: 'images/source/**/*.svg',
  svgDest: 'images/optimized'
};

// BrowserSync
function browserSyncServe(done) {
  browsersync.init({proxy: config.localURL, port: 3000});
  done();
}

function browserSyncReload(done){
  browsersync.reload();
  done();
}

// Drush cache clear (css-js)
function drushCC() {
  return gulp.src('', { read: false })
  .pipe(shell(['drush cc css-js']))
  .pipe(
    notify({
      title: 'Caches cleared',
      message: 'Drupal CSS/JS caches cleared.',
      onLast: true
    })
  );
}

// Drush cache rebuild
function drushCR() {
  return gulp.src('', { read: false })
  .pipe(shell(['drush cr']))
  .pipe(
    notify({
      title: 'Cache rebuilt',
      message: 'Drupal cache rebuilt.',
      onLast: true
    })
  )
  .pipe(browsersync.reload({ stream: true }));
}

// Compile Sass
function compileSass() {
  return gulp.src(paths.sassSrc)
  .pipe(sourcemaps.init())
  .pipe(sassglob())
  .pipe(
    sass({
      outputStyle: 'expanded',
      sourcemaps: true
    }).on('error', notify.onError('<%= error.message %>')) // alt sass.logError
  )
  .pipe(postcss([ cssnano() ]))
  .pipe(sourcemaps.write('.')) // Write to same directory where CSS will live (allows for the proper reference to Sourcemap in CSS file!)
  .pipe(gulp.dest(paths.sassDest))
  .pipe(browsersync.stream());
}
exports.sass = compileSass // enables 'gulp sass' from the terminal

// Sass Lint
function sassLint() {
  return gulp.src(paths.sassSrc)
  .pipe(sasslint({ configFile: 'sass-lint.yml' }))
  .pipe(sasslint.format())
  .pipe(sasslint.failOnError());
}

// Compile JS Scripts
function compileScripts() {
  return (
    gulp.src(paths.jsSrc)
    .pipe(sourcemaps.init())
    .pipe(uglify()) // minify js
    .pipe(sourcemaps.write('.'))
    .pipe(gulp.dest(paths.jsDest)));
}
exports.scripts = compileScripts // enables 'gulp scripts' from the terminal


// Javascript Lint
function jsLint() {
  return gulp.src(paths.jsSrc)
  .pipe(eslint())
  .pipe(eslint.format())
  .pipe(eslint.failAfterError());
}

// Flush and Reload
exports.flush = gulp.series(drushCR, browserSyncReload); // enables 'gulp flush' from the terminal

// Watch Task
function watchTask() {
  gulp.watch(paths.sassSrc, gulp.series(compileSass, browserSyncReload));
  gulp.watch(paths.jsSrc, gulp.series(compileScripts, browserSyncReload));
  gulp.watch(paths.template, gulp.series(drushCR, browserSyncReload));
}
exports.watch = watchTask // enables 'gulp watch' from the terminal

// Default task
exports.default = gulp.series(compileSass, compileScripts, browserSyncServe, watchTask);
