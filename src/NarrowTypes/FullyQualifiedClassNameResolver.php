<?php declare(strict_types=1);

namespace Forrest79\NarrowTypes;

use PhpParser;

/**
 * @phpstan-import-type Type from TypeParser
 */
final class FullyQualifiedClassNameResolver
{
	/** @var array<string, PhpParser\NameContext|FALSE> */
	private static array $nameContextsCache = [];


	public static function resolve(string $filename, string $class): string
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
