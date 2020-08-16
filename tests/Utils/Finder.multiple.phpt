<?php

/**
 * Test: Nette\Utils\Finder multiple sources.
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


test('recursive', function () {
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


test('non-recursive', function () {
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
