module.exports = function (grunt) {
	("use strict");

	// Project configuration
	grunt.initConfig({
		pkg: grunt.file.readJSON("package.json"),

		addtextdomain: {
			options: {
				textdomain: "cool-kids-network-wp",
			},
			update_all_domains: {
				options: {
					updateDomains: true,
				},
				src: [
					"*.php",
					"**/*.php",
					"!vendor/**/*",
					"!.git/**/*",
					"!bin/**/*",
					"!node_modules/**/*",
					"!tests/**/*",
				],
			},
		},
		wp_readme_to_markdown: {
			your_target: {
				files: {
					"README.md": "readme.txt",
				},
			},
		},
		//build plugin zip
		compress: {
			main: {
				options: {
					archive: "archive/cool-kids-network-wp.zip",
				},
				files: [
					{
						src: [
							"app/**",
							"assets/**",
							"core/**",
							"vendor/**",
							"cool-kids-network-wp.php",
							"readme.md",
							"composer.json",
						],
						dest: "cool-kids-network-wp/",
						filter: function (filepath) {
							// Ignore .logs, .gitignore, .map files, and .css files in assets/js
							return !filepath.match(
								/\.logs$|\.gitignore$|\.map$|assets\/js\/.*\.css$/,
							);
						},
					},
				],
			},
		},
	});

	// Load the required tasks
	grunt.loadNpmTasks("grunt-wp-readme-to-markdown");
	grunt.loadNpmTasks("grunt-contrib-compress");
	// Register the default task
	grunt.registerTask("default", ["readme"]);
	grunt.registerTask("readme", ["wp_readme_to_markdown"]);

	// Register the composer_optimise task
	grunt.registerTask(
		"composer_optimise",
		"Run composer optimise",
		function () {
			const done = this.async();
			require("child_process").exec(
				"composer install --no-dev --prefer-dist --optimize-autoloader",
				(err, stdout, stderr) => {
					if (err) {
						grunt.log.error(err);
						done(false);
					} else {
						grunt.log.writeln(stdout);
						done();
					}
				},
			);
		},
	);

	// Register the delete_readme task
	grunt.registerTask("delete_readme", "Delete README.md", function () {
		const fs = require("fs");
		const done = this.async();
		fs.unlink("README.md", function (err) {
			if (err) {
				grunt.log.error(err);
				done(false);
			} else {
				grunt.log.writeln("README.md deleted successfully.");
				done();
			}
		});
	});

	// Empty archive folder
	grunt.registerTask("empty_archive", "Empty archive folder", function () {
		const fs = require("fs-extra"); // Use fs-extra
		const done = this.async();
		fs.emptyDir("archive", function (err) {
			if (err) {
				grunt.log.error(err);
				done(false);
			} else {
				grunt.log.writeln("Archive folder emptied successfully.");
				done();
			}
		});
	});

	// Register the archive_plugin task
	grunt.registerTask("archive_plugin", ["compress"]);

	// Register the build task
	grunt.registerTask("build", [
		"composer_optimise", // Optimize composer dependencies
		"empty_archive", // Empty archive folder
		"readme", // Convert README.txt to README.md
		"archive_plugin", // Create a zip archive of the plugin
		"delete_readme", // Delete README.md after archiving
	]);
};
