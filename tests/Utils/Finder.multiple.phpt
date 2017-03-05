<?php

/**
 * Test: Nette\Utils\Finder multiple sources.
 */

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


test(function () { // recursive
	$finder = Finder::find('*')->from('files/subdir/subdir2', 'files/images');
	Assert::same([
		'files/images/logo.gif',
		'files/subdir/subdir2/file.txt',
	], export($finder));


	$finder = Finder::find('*')->from(['files/subdir/subdir2', 'files/images']);
	Assert::same([
		'files/images/logo.gif',
		'files/subdir/subdir2/file.txt',
	], export($finder));

	Assert::exception(function () {
		Finder::find('*')->from('files/subdir/subdir2')->from('files/images');
	}, Nette\InvalidStateException::class, '');
});


test(function () { // non-recursive
	$finder = Finder::find('*')->in('files/subdir/subdir2', 'files/images');
	Assert::same([
		'files/images/logo.gif',
		'files/subdir/subdir2/file.txt',
	], export($finder));


	$finder = Finder::find('*')->in(['files/subdir/subdir2', 'files/images']);
	Assert::same([
		'files/images/logo.gif',
		'files/subdir/subdir2/file.txt',
	], export($finder));

	Assert::exception(function () {
		Finder::find('*')->in('files/subdir/subdir2')->in('files/images');
	}, Nette\InvalidStateException::class, '');
});
