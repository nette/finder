<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Nette\Utils;

use Nette;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;


/**
 * Finder allows searching through directory trees using iterator.
 *
 * <code>
 * Finder::findFiles('*.php')
 *     ->size('> 10kB')
 *     ->from('.')
 *     ->exclude('temp');
 * </code>
 */
class Finder implements \IteratorAggregate, \Countable
{
	use Nette\SmartObject;

	/** @var callable  extension methods */
	private static $extMethods = [];

	/** @var array */
	private $paths = [];

	/** @var array of filters */
	private $groups = [];

	/** @var array filter for recursive traversing */
	private $exclude = [];

	/** @var int */
	private $order = RecursiveIteratorIterator::SELF_FIRST;

	/** @var int */
	private $maxDepth = -1;

	/** @var array */
	private $cursor;


	/**
	 * Begins search for files matching mask and all directories.
	 * @param  string|string[]  $masks
	 * @return static
	 */
	public static function find(...$masks)
	{
		$masks = $masks && is_array($masks[0]) ? $masks[0] : $masks;
		return (new static)->select($masks, 'isDir')->select($masks, 'isFile');
	}


	/**
	 * Begins search for files matching mask.
	 * @param  string|string[]  $masks
	 * @return static
	 */
	public static function findFiles(...$masks)
	{
		$masks = $masks && is_array($masks[0]) ? $masks[0] : $masks;
		return (new static)->select($masks, 'isFile');
	}


	/**
	 * Begins search for directories matching mask.
	 * @param  string|string[]  $masks
	 * @return static
	 */
	public static function findDirectories(...$masks)
	{
		$masks = $masks && is_array($masks[0]) ? $masks[0] : $masks;
		return (new static)->select($masks, 'isDir');
	}


	/**
	 * Creates filtering group by mask & type selector.
	 * @return static
	 */
	private function select(array $masks, string $type)
	{
		$this->cursor = &$this->groups[];
		$pattern = self::buildPattern($masks);
		if ($type || $pattern) {
			$this->filter(function (RecursiveDirectoryIterator $file) use ($type, $pattern) {
				return !$file->isDot()
					&& (!$type || $file->$type())
					&& (!$pattern || preg_match($pattern, '/' . strtr($file->getSubPathName(), '\\', '/')));
			});
		}
		return $this;
	}


	/**
	 * Searches in the given folder(s).
	 * @param  string|string[]  $paths
	 * @return static
	 */
	public function in(...$paths)
	{
		$this->maxDepth = 0;
		return $this->from(...$paths);
	}


	/**
	 * Searches recursively from the given folder(s).
	 * @param  string|string[]  $paths
	 * @return static
	 */
	public function from(...$paths)
	{
		if ($this->paths) {
			throw new Nette\InvalidStateException('Directory to search has already been specified.');
		}
		$this->paths = is_array($paths[0]) ? $paths[0] : $paths;
		$this->cursor = &$this->exclude;
		return $this;
	}


	/**
	 * Shows folder content prior to the folder.
	 * @return static
	 */
	public function childFirst()
	{
		$this->order = RecursiveIteratorIterator::CHILD_FIRST;
		return $this;
	}


	/**
	 * Converts Finder pattern to regular expression.
	 * @return string|null
	 */
	private static function buildPattern(array $masks)
	{
		$pattern = [];
		foreach ($masks as $mask) {
			$mask = rtrim(strtr($mask, '\\', '/'), '/');
			$prefix = '';
			if ($mask === '') {
				continue;

			} elseif ($mask === '*') {
				return null;

			} elseif ($mask[0] === '/') { // absolute fixing
				$mask = ltrim($mask, '/');
				$prefix = '(?<=^/)';
			}
			$pattern[] = $prefix . strtr(preg_quote($mask, '#'),
				['\*\*' => '.*', '\*' => '[^/]*', '\?' => '[^/]', '\[\!' => '[^', '\[' => '[', '\]' => ']', '\-' => '-']);
		}
		return $pattern ? '#/(' . implode('|', $pattern) . ')\z#i' : null;
	}


	/********************* iterator generator ****************d*g**/


	/**
	 * Get the number of found files and/or directories.
	 */
	public function count(): int
	{
		return iterator_count($this->getIterator());
	}


	/**
	 * Returns iterator.
	 */
	public function getIterator(): \Iterator
	{
		if (!$this->paths) {
			throw new Nette\InvalidStateException('Call in() or from() to specify directory to search.');

		} elseif (count($this->paths) === 1) {
			return $this->buildIterator((string) $this->paths[0]);

		} else {
			$iterator = new \AppendIterator();
			$iterator->append($workaround = new \ArrayIterator(['workaround PHP bugs #49104, #63077']));
			foreach ($this->paths as $path) {
				$iterator->append($this->buildIterator((string) $path));
			}
			unset($workaround[0]);
			return $iterator;
		}
	}


	/**
	 * Returns per-path iterator.
	 */
	private function buildIterator(string $path): \Iterator
	{
		$iterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::FOLLOW_SYMLINKS);

		if ($this->exclude) {
			$iterator = new \RecursiveCallbackFilterIterator($iterator, function ($foo, $bar, RecursiveDirectoryIterator $file) {
				if (!$file->isDot() && !$file->isFile()) {
					foreach ($this->exclude as $filter) {
						if (!$filter($file)) {
							return false;
						}
					}
				}
				return true;
			});
		}

		if ($this->maxDepth !== 0) {
			$iterator = new RecursiveIteratorIterator($iterator, $this->order);
			$iterator->setMaxDepth($this->maxDepth);
		}

		$iterator = new \CallbackFilterIterator($iterator, function ($foo, $bar, \Iterator $file) {
			while ($file instanceof \OuterIterator) {
				$file = $file->getInnerIterator();
			}

			foreach ($this->groups as $filters) {
				foreach ($filters as $filter) {
					if (!$filter($file)) {
						continue 2;
					}
				}
				return true;
			}
			return false;
		});

		return $iterator;
	}


	/********************* filtering ****************d*g**/


	/**
	 * Restricts the search using mask.
	 * Excludes directories from recursive traversing.
	 * @param  string|string[]  $masks
	 * @return static
	 */
	public function exclude(...$masks)
	{
		$masks = $masks && is_array($masks[0]) ? $masks[0] : $masks;
		$pattern = self::buildPattern($masks);
		if ($pattern) {
			$this->filter(function (RecursiveDirectoryIterator $file) use ($pattern) {
				return !preg_match($pattern, '/' . strtr($file->getSubPathName(), '\\', '/'));
			});
		}
		return $this;
	}


	/**
	 * Restricts the search using callback.
	 * @param  callable  $callback  function (RecursiveDirectoryIterator $file)
	 * @return static
	 */
	public function filter(callable $callback)
	{
		$this->cursor[] = $callback;
		return $this;
	}


	/**
	 * Limits recursion level.
	 * @return static
	 */
	public function limitDepth(int $depth)
	{
		$this->maxDepth = $depth;
		return $this;
	}


	/**
	 * Restricts the search by size.
	 * @param  string  $operator  "[operator] [size] [unit]" example: >=10kB
	 * @return static
	 */
	public function size(string $operator, int $size = null)
	{
		if (func_num_args() === 1) { // in $operator is predicate
			if (!preg_match('#^(?:([=<>!]=?|<>)\s*)?((?:\d*\.)?\d+)\s*(K|M|G|)B?\z#i', $operator, $matches)) {
				throw new Nette\InvalidArgumentException('Invalid size predicate format.');
			}
			list(, $operator, $size, $unit) = $matches;
			static $units = ['' => 1, 'k' => 1e3, 'm' => 1e6, 'g' => 1e9];
			$size *= $units[strtolower($unit)];
			$operator = $operator ?: '=';
		}
		return $this->filter(function (RecursiveDirectoryIterator $file) use ($operator, $size) {
			return self::compare($file->getSize(), $operator, $size);
		});
	}


	/**
	 * Restricts the search by modified time.
	 * @param  string  $operator  "[operator] [date]" example: >1978-01-23
	 * @param  string|int|\DateTimeInterface  $date
	 * @return static
	 */
	public function date(string $operator, $date = null)
	{
		if (func_num_args() === 1) { // in $operator is predicate
			if (!preg_match('#^(?:([=<>!]=?|<>)\s*)?(.+)\z#i', $operator, $matches)) {
				throw new Nette\InvalidArgumentException('Invalid date predicate format.');
			}
			list(, $operator, $date) = $matches;
			$operator = $operator ?: '=';
		}
		$date = DateTime::from($date)->format('U');
		return $this->filter(function (RecursiveDirectoryIterator $file) use ($operator, $date) {
			return self::compare($file->getMTime(), $operator, $date);
		});
	}


	/**
	 * Compares two values.
	 */
	public static function compare($l, string $operator, $r): bool
	{
		switch ($operator) {
			case '>':
				return $l > $r;
			case '>=':
				return $l >= $r;
			case '<':
				return $l < $r;
			case '<=':
				return $l <= $r;
			case '=':
			case '==':
				return $l == $r;
			case '!':
			case '!=':
			case '<>':
				return $l != $r;
			default:
				throw new Nette\InvalidArgumentException("Unknown operator $operator.");
		}
	}


	/********************* extension methods ****************d*g**/


	public function __call(string $name, array $args)
	{
		return isset(self::$extMethods[$name])
			? (self::$extMethods[$name])($this, ...$args)
			: parent::__call($name, $args);
	}


	public static function extensionMethod(string $name, callable $callback)
	{
		self::$extMethods[$name] = $callback;
	}
}
