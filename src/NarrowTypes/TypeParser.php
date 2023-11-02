<?php declare(strict_types=1);

namespace Forrest79\NarrowTypes;

/**
 * @phpstan-type Type array{type: string, key?: string, value?: string, class?: string}
 */
final class TypeParser
{
	/** @var array<string, array<string, list<Type>>> */
	private static array $cache = [];

	public const MIXED = 'mixed';
	public const NULL = 'null';
	public const INT = 'int';
	public const FLOAT = 'float';
	public const STRING = 'string';
	public const BOOL = 'bool';
	public const CALLABLE = 'callable';
	public const ARRAY = 'array';
	public const LIST = 'list';
	public const OBJECT = 'object';

	private const SUPPORTED_TYPES = [
		self::MIXED,
		self::NULL,
		self::INT,
		self::FLOAT,
		self::STRING,
		self::BOOL,
		self::CALLABLE,
		self::ARRAY,
		self::LIST,
		self::OBJECT,
	];

	private string $filename;

	private string $typeDescription;

	private int $i = 0;

	/** @var non-empty-list<string> */
	private array $parts;


	private function __construct(string $filename, string $type)
	{
		$this->filename = $filename;
		$this->typeDescription = $type;
	}


	/**
	 * @return list<Type>
	 */
	public function parseTypes(): array
	{
		$parts = \preg_split('#(\||<|>|,)#', $this->typeDescription, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
		if (($parts === FALSE) || ($parts === [])) {
			$this->throwBadTypeDescription();
		}

		$this->parts = $parts;

		$parsedTypes = [];

		while (TRUE) {
			$parsedType = $this->readNextType();
			if ($parsedType === NULL) {
				break;
			}

			$parsedTypes[] = $parsedType;
		}

		return $parsedTypes;
	}


	/**
	 * @return Type|NULL
	 */
	private function readNextType(): array|NULL
	{
		$parsedType = NULL;

		$count = count($this->parts);

		$waitingForType = TRUE;
		$readingIterable = FALSE;
		$iterableDeep = 0;
		$iterableType = '';
		$iterableKeyIsRead = FALSE;
		for (; $this->i < $count; $this->i++) {
			$part = trim($this->parts[$this->i]);

			if ($waitingForType) {
				if (in_array($part, ['|', '<', '>', ','], TRUE)) {
					$this->throwBadTypeDescription();
				}

				if (in_array(strtolower($part), self::SUPPORTED_TYPES, TRUE)) {
					$parsedType = ['type' => strtolower($part)];
				} else {
					$parsedType = [
						'type' => self::OBJECT,
						'class' => str_starts_with($part, '\\')
							? $part
							: FullyQualifiedClassNameResolver::resolve($this->filename, $part),
					];
				}

				$waitingForType = FALSE;
			} else if (!$readingIterable) {
				if ($part === '|') {
					$this->i++;

					assert(isset($parsedType['type']));
					return $parsedType;
				} else if ($part === '<') {
					if (!in_array($parsedType['type'], [self::ARRAY, self::LIST], TRUE)) {
						$this->throwBadTypeDescription();
					}

					if ($parsedType['type'] === self::ARRAY) {
						$parsedType['key'] = self::INT . '|' . self::STRING;
					}

					$parsedType['value'] = self::MIXED;

					$readingIterable = TRUE;
					$iterableDeep++;
				} else {
					$this->throwBadTypeDescription();
				}
			} else {
				if ($part === '<') {
					$iterableDeep++;
				} else if ($part === '>') {
					$iterableDeep--;
					if ($iterableDeep === 0) {
						$readingIterable = FALSE;
						$parsedType['value'] = $iterableType;
						$iterableType = '';
						continue;
					}
				} else if (($iterableDeep === 1) && ($part === ',')) {
					if (($parsedType['type'] === 'list') || $iterableKeyIsRead) {
						$this->throwBadTypeDescription();
					}

					$parsedType['key'] = $iterableType;
					$iterableType = '';
					$iterableKeyIsRead = TRUE;
					continue;
				}

				$iterableType .= $part;
			}
		}

		if ($iterableDeep > 0) {
			$this->throwBadTypeDescription();
		}

		assert($parsedType === NULL || isset($parsedType['type']));
		return $parsedType;
	}


	private function throwBadTypeDescription(): never
	{
		throw new \InvalidArgumentException(sprintf('Bad type description \'%s\'.', $this->typeDescription));
	}


	/**
	 * @return list<Type>
	 */
	public static function parse(string $filename, string $type): array
	{
		if (!isset(self::$cache[$filename][$type])) {
			self::$cache[$filename][$type] = (new self($filename, $type))->parseTypes();
		}

		return self::$cache[$filename][$type];
	}

}
