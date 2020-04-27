/* eslint-disable no-undef */
'use strict';

const gulp = require('gulp');
const $ = require('gulp-load-plugins')({ pattern: ['gulp-*'] });


gulp.task('php', () => gulp.src(['src/**/*.php'])
	.pipe($.plumber())
	.pipe($.changed('./dist'))
	.pipe(gulp.dest('./dist'))
);

gulp.task('watch', () => {
	gulp.watch('src/**/*.php', gulp.series('php'));
});

gulp.task('build', gulp.parallel('php'));

gulp.task('default', gulp.series('build', 'watch'));
