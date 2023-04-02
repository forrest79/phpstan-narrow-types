<?php declare(strict_types=1);

namespace Forrest79\NarrowTypes;

use PhpParser;

/**
 * @phpstan-type Type array{type: string, key?: string, value?: string, class?: string}
 */
final class Helper
{
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

	public const SUPPORTED_TYPES = [self::MIXED, self::NULL, self::INT, self::FLOAT, self::STRING, self::BOOL, self::CALLABLE, self::ARRAY, self::LIST, self::OBJECT];

	/** @var array<string, list<Type>> */
	private static array $parsedTypesCache = [];

	/** @var array<string, PhpParser\NameContext|FALSE> */
	private static array $nameContextsCache = [];


	/**
	 * @return list<Type>
	 */
	public static function parseType(string $filename, string $type): array
	{
		if (!isset(self::$parsedTypesCache[$type])) {
			$parsedTypes = [];

			if (str_starts_with($type, self::ARRAY . '<') && str_ends_with($type, '>')) {
				$arrayTypes = substr($type, 6, -1); // array<***, ***>
				$arrayTypesParts = explode(',', $arrayTypes, 2);

				if (count($arrayTypesParts) === 1) { // array without key type
					array_unshift($arrayTypesParts, self::INT . '|' . self::STRING);
				}

				$parsedTypes[] = [
					'type' => self::ARRAY,
					'key' => trim($arrayTypesParts[0]),
					'value' => trim($arrayTypesParts[1]),
				];
			} else if (str_starts_with($type, self::LIST . '<') && str_ends_with($type, '>')) {
				$parsedTypes[] = [
					'type' => self::LIST, // list<***>,
					'value' => substr($type, 5, -1),
				];
			} else {
				$typeParts = explode('|', $type);
				foreach ($typeParts as $typePart) {
					$parsedType = [];

					$typePart = trim($typePart);
					if (in_array(strtolower($typePart), self::SUPPORTED_TYPES, TRUE)) {
						$parsedType['type'] = strtolower($typePart);
					} else {
						$parsedType['type'] = self::OBJECT;
						$parsedType['class'] = str_starts_with($typePart, '\\') ? $typePart : self::resolveFullyQualifiedClassName($filename, $typePart);
					}

					$parsedTypes[] = $parsedType;
				}
			}

			self::$parsedTypesCache[$type] = $parsedTypes;
		}

		return self::$parsedTypesCache[$type];
	}


	public static function resolveFullyQualifiedClassName(string $filename, string $class): string
	{
		if (!isset(self::$nameContextsCache[$filename])) {
			self::$nameContextsCache[$filename] = self::createNameContext($filename) ?? FALSE;
		}

		$nameContext = self::$nameContextsCache[$filename];
		if ($nameContext === FALSE) {
			return $class;
		}

		return $nameContext->getResolvedClassName(new PhpParser\Node\Name($class))->toString();
	}


	private static function createNameContext(string|NULL $filename): PhpParser\NameContext|NULL
	{
		if ($filename === NULL) {
			return NULL;
		}

		$parser = (new PhpParser\ParserFactory())->create(PhpParser\ParserFactory::PREFER_PHP7);
		$traverser = new PhpParser\NodeTraverser();
		$nameResolver = new PhpParser\NodeVisitor\NameResolver();
		$traverser->addVisitor($nameResolver);
		$nameContext = $nameResolver->getNameContext();

		try {
			$code = file_get_contents($filename);
			if ($code === FALSE) {
				return NULL;
			}

			$stmt = $parser->parse($code);
			if ($stmt === NULL) {
				return NULL;
			}

			$traverser->traverse($stmt);

			return $nameContext;
		} catch (\Throwable) {
			// ignore errors
		}

		return NULL;
	}

}
