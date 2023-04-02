<?php declare(strict_types=1);

namespace Forrest79;

use Forrest79\NarrowTypes\Helper;
use PHPStan\ShouldNotHappenException;

/**
 * @phpstan-type Type array{type: string, key?: string, value?: string, class?: string}
 */
final class NarrowTypes
{
	private const HAS_IS_FUNCTION_TYPES = [
		Helper::NULL,
		Helper::INT,
		Helper::FLOAT,
		Helper::STRING,
		Helper::BOOL,
		Helper::CALLABLE,
		Helper::OBJECT,
	];


	public static function isType(mixed $value, string $type): bool
	{
		$filename = '';
		foreach (debug_backtrace() as $item) {
			if (!str_starts_with($item['file'] ?? '', __DIR__)) {
				$filename = $item['file'] ?? '';
				break;
			}
		}

		return self::checkType($filename, $value, $type);
	}


	private static function checkType(string $filename, mixed $value, string $type): bool
	{
		foreach (Helper::parseType($filename, $type) as $parsedType) {
			$checkType = $parsedType['type'];

			if ($checkType === Helper::MIXED) {
				return TRUE;
			} else if (in_array($checkType, self::HAS_IS_FUNCTION_TYPES, TRUE)) {
				if (call_user_func('is_' . $checkType, $value) === TRUE) {
					return TRUE;
				}
			} else if ($checkType === Helper::ARRAY) {
				if (is_array($value)) {
					if (isset($parsedType['key']) && isset($parsedType['value'])) {
						foreach ($value as $k => $v) {
							if (!self::checkType($filename, $k, $parsedType['key']) || !self::checkType($filename, $v, $parsedType['value'])) {
								return FALSE;
							}
						}
					}

					return TRUE;
				}
			} else if ($checkType === Helper::LIST) {
				if (is_array($value) && array_is_list($value)) {
					if (isset($parsedType['value'])) {
						foreach ($value as $v) {
							if (!self::checkType($filename, $v, $parsedType['value'])) {
								return FALSE;
							}
						}
					}

					return TRUE;
				}
			} else if ($checkType === Helper::OBJECT) {
				if (is_object($value)) {
					if (isset($parsedType['class'])) {
						if (!($value instanceof $parsedType['class'])) {
							return FALSE;
						}
					}

					return TRUE;
				}
			} else {
				throw new ShouldNotHappenException(sprintf('Invalid type to check \'%s\'.', $checkType));
			}
		}

		return FALSE;
	}

}
