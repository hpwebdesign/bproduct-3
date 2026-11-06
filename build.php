#!/usr/bin/env php
<?php
/**
 * Builds build/bproduct.v-{version}.ocmod.zip.
 * The version is bumped automatically when packaged files changed since the last build.
 * By default the zip is a patch: install.xml plus only the files changed since the last build.
 *
 * Usage:
 *   php build.php                  bump (if changed) and build a patch
 *   php build.php --full           bump (if changed) and build the full package
 *   php build.php --no-bump        build without touching the version
 *   php build.php --version=1.2.0  set an explicit version and build
 *   php build.php --baseline       record the current files as released, build nothing
 */

$root      = __DIR__;
$prefix    = 'bproduct';
$stateFile = $root . '/.build-state.json';
$buildDir  = $root . '/build';

// Files that carry the version string, with a pattern of three groups around it
$versioned = array(
	'install.xml'                                      => '~(<version>)([^<]*)(</version>)~',
	'upload/admin/controller/extension/module/bproduct.php' => "~(\\\$version\\s*=\\s*')([^']*)(')~",
	'README.md'                                        => '~(\*\*Version:\*\* )([0-9.]+)()~',
);

$options = getopt('', array('no-bump', 'full', 'baseline', 'version:'));

function fail($message)
{
	fwrite(STDERR, $message . "\n");
	exit(1);
}

function packageFiles($root)
{
	$files = array('install.xml');
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/upload', FilesystemIterator::SKIP_DOTS));

	foreach ($iterator as $file) {
		if ($file->isFile() && $file->getFilename() !== '.DS_Store') {
			$files[] = substr($file->getPathname(), strlen($root) + 1);
		}
	}

	sort($files);

	return $files;
}

// Hashes ignore the version strings, so a bump alone does not count as a change
function fileHashes($root, array $files, array $versioned)
{
	$hashes = array();

	foreach ($files as $path) {
		$content = file_get_contents($root . '/' . $path);

		if (isset($versioned[$path])) {
			$content = preg_replace($versioned[$path], '${1}0${3}', $content);
		}

		$hashes[$path] = hash('sha256', $content);
	}

	return $hashes;
}

function readVersion($root)
{
	if (!preg_match('~<version>([^<]+)</version>~', file_get_contents($root . '/install.xml'), $match)) {
		fail('Cannot find <version> in install.xml');
	}

	return trim($match[1]);
}

function bump($version)
{
	$parts = explode('.', $version);
	$parts[count($parts) - 1] = (int)end($parts) + 1;

	return implode('.', $parts);
}

function writeVersion($root, array $versioned, $version)
{
	foreach ($versioned as $path => $pattern) {
		$content = file_get_contents($root . '/' . $path);
		$updated = preg_replace($pattern, '${1}' . $version . '${3}', $content, 1, $count);

		if (!$count) {
			fail('Cannot find the version string in ' . $path);
		}

		file_put_contents($root . '/' . $path, $updated);
	}
}

function saveState($stateFile, $version, array $hashes)
{
	file_put_contents($stateFile, json_encode(array('version' => $version, 'files' => $hashes), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

$files   = packageFiles($root);
$hashes  = fileHashes($root, $files, $versioned);
$version = readVersion($root);

if (isset($options['baseline'])) {
	saveState($stateFile, $version, $hashes);
	echo "Baseline recorded at version $version (" . count($files) . " files)\n";
	exit(0);
}

$state = is_file($stateFile) ? json_decode(file_get_contents($stateFile), true) : null;
$known = ($state && !empty($state['files'])) ? $state['files'] : array();

$changedFiles = array();

foreach ($hashes as $path => $hash) {
	if (!isset($known[$path]) || $known[$path] !== $hash) {
		$changedFiles[] = $path;
	}
}

$removed = array_diff(array_keys($known), array_keys($hashes));
$changed = $changedFiles || $removed;

if (isset($options['version'])) {
	if (!preg_match('~^\d+(\.\d+)+$~', $options['version'])) {
		fail('Invalid version: ' . $options['version']);
	}

	$new = $options['version'];
} elseif ($changed && !isset($options['no-bump'])) {
	$new = bump($version);
} else {
	$new = $version;
}

if ($new !== $version) {
	writeVersion($root, $versioned, $new);
	echo "Version: $version -> $new\n";
} else {
	echo "Version: $version" . ($changed ? " (changed files, not bumped)\n" : " (no changes)\n");
}

if (!$changed && !isset($options['full']) && !isset($options['version'])) {
	echo "Nothing to build. Use --full to build the whole package.\n";
	exit(0);
}

$full = isset($options['full']) || !$known;

$include = $full ? $files : array_values(array_unique(array_merge(array('install.xml'), $changedFiles)));
sort($include);

if (!class_exists('ZipArchive')) {
	fail('PHP zip extension is required');
}

if (!is_dir($buildDir) && !mkdir($buildDir, 0775, true)) {
	fail('Cannot create build/');
}

$output = $buildDir . '/' . $prefix . '.v-' . $new . '.ocmod.zip';

if (is_file($output)) {
	unlink($output);
}

$zip = new ZipArchive();

if ($zip->open($output, ZipArchive::CREATE) !== true) {
	fail('Cannot create ' . $output);
}

foreach ($include as $path) {
	$zip->addFile($root . '/' . $path, $path);
}

$zip->close();

// Re-read the files so the state matches what was just written (version strings are ignored by the hash)
saveState($stateFile, $new, fileHashes($root, packageFiles($root), $versioned));

echo 'Built: build/' . basename($output) . ' (' . count($include) . ' file(s), ' . ($full ? 'full package' : 'patch') . ")\n";

if ($removed) {
	echo "Removed since last build (delete on the server if needed):\n  " . implode("\n  ", $removed) . "\n";
}
