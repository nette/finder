<?php

/**
 * Test: Nette\Utils\Finder mask tests.
 */

declare(strict_types=1);

use Nette\Utils\Finder;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


function export($iterator)
{
	$arr = [];
	foreach ($iterator as $key => $value) {
		$arr[] = strtr($key, '\\', '/');
	}
	sort($arr);
	return $arr;
}


test(function () {
	Assert::exception(function () {
		$finder = Finder::find();
	}, Nette\InvalidArgumentException::class, "Argument 'masks' cannot be empty.");
});


test(function () {
	Assert::exception(function () {
		$finder = Finder::findDirectories();
	}, Nette\InvalidArgumentException::class, "Argument 'masks' cannot be empty.");
});


test(function () {
	Assert::exception(function () {
		$finder = Finder::findFiles();
	}, Nette\InvalidArgumentException::class, "Argument 'masks' cannot be empty.");
});


test(function () { // multiple mask
	$finder = Finder::findFiles('*.txt', '*.gif')->from('files');
	Assert::same([
		'files/file.txt',
		'files/images/logo.gif',
		'files/subdir/file.txt',
		'files/subdir/subdir2/file.txt',
	], export($finder));
});


test(function () {
	$finder = Finder::findFiles(['*.txt', '*.gif'])->from('files');
	Assert::same([
		'files/file.txt',
		'files/images/logo.gif',
		'files/subdir/file.txt',
		'files/subdir/subdir2/file.txt',
	], export($finder));
});


test(function () { // * mask
	$finder = Finder::findFiles('*.txt', '*')->in('files/subdir');
	Assert::same([
		'files/subdir/file.txt',
		'files/subdir/readme',
	], export($finder));
});


test(function () { // *.* mask
	$finder = Finder::findFiles('*.*')->in('files/subdir');
	Assert::same([
		'files/subdir/file.txt',
	], export($finder));
});


// subdir excluding mask
$finder = Finder::findFiles('*')->exclude('*i*/*')->from('files');
Assert::same([
	'files/file.txt',
], export($finder));


test(function () { // subdir mask
	$finder = Finder::findFiles('*/*2/*')->from('files');
	Assert::same([
		'files/subdir/subdir2/file.txt',
	], export($finder));
});


test(function () { // excluding mask
	$finder = Finder::findFiles('*')->exclude('*i*')->in('files/subdir');
	Assert::same([
		'files/subdir/readme',
	], export($finder));
});


test(function () { // subdir excluding mask
	$finder = Finder::findFiles('*')->exclude('*i*/*')->from('files');
	Assert::same([
		'files/file.txt',
	], export($finder));
});


test(function () { // complex mask
	$finder = Finder::findFiles('*[efd][a-z][!a-r]*')->from('files');
	Assert::same([
		'files/images/logo.gif',
	], export($finder));
});


test(function () {
	$finder = Finder::findFiles('*2*/fi??.*')->from('files');
	Assert::same([
		'files/subdir/subdir2/file.txt',
	], export($finder));
});


test(function () { // anchored
	$finder = Finder::findFiles('/f*')->from('files');
	Assert::same([
		'files/file.txt',
	], export($finder));
});


test(function () {
	$finder = Finder::findFiles('/*/f*')->from('files');
	Assert::same([
		'files/subdir/file.txt',
	], export($finder));
});


test(function () { // multidirs mask
	$finder = Finder::findFiles('/**/f*')->from('files');
	Assert::same([
		'files/subdir/file.txt',
		'files/subdir/subdir2/file.txt',
	], export($finder));
});
