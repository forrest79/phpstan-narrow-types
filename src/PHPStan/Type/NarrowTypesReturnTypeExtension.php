<?php declare(strict_types=1);

namespace Forrest79\PHPStan\Type;

use Forrest79\NarrowTypes\TypeParser;
use PHPStan\Analyser;
use PHPStan\Analyser\Scope;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type;
use PhpParser\Node;

abstract class NarrowTypesReturnTypeExtension implements Analyser\TypeSpecifierAwareExtension
{
	private Analyser\TypeSpecifier $typeSpecifier;


	abstract protected static function getIsTypeName(): string;


	final protected static function isSupported(string $methodName, int $argCount): bool
	{
		return ($methodName === static::getIsTypeName()) && ($argCount === 2);
	}


	/**
	 * @param array<Node\Arg> $args {0 -> checked variable, 1 -> key for array, value for list, 2 -> value for array}
	 */
	final protected function narrowTypes(
		string $methodOrFunctionName,
		array $args,
		Scope $scope,
	): Analyser\SpecifiedTypes
	{
		if ($methodOrFunctionName === static::getIsTypeName()) {
			$valueArg = $args[1]->value;
			$valueScopedType = $scope->getType($valueArg);

			$valueTypeDescriptionString = $valueScopedType->getConstantStrings();
			if (count($valueTypeDescriptionString) === 1) {
				$valueTypeDescription = $valueTypeDescriptionString[0]->getValue();

				$type = self::narrowType($scope->getFile(), $valueTypeDescription);
			} else { // should not happen
				$type = new Type\MixedType();
			}
		} else {
			throw new ShouldNotHappenException(sprintf('Unsupported method/function \'%s\' in %s.', $methodOrFunctionName, static::class));
		}

		return $this->typeSpecifier->create($args[0]->value, $type, Analyser\TypeSpecifierContext::createTruthy(), $scope);
	}


	private static function narrowType(string $filename, string $type): Type\Type
	{
		$narrowType = [];
		foreach (TypeParser::parse($filename, $type) as $parsedType) {
			$checkType = $parsedType['type'];

			if ($checkType === TypeParser::MIXED) {
				$narrowType[] = new Type\MixedType();
			} else if ($checkType === TypeParser::NULL) {
				$narrowType[] = new Type\NullType();
			} else if ($checkType === TypeParser::INT) {
				$narrowType[] = new Type\IntegerType();
			} else if ($checkType === TypeParser::FLOAT) {
				$narrowType[] = new Type\FloatType();
			} else if ($checkType === TypeParser::STRING) {
				$narrowType[] = new Type\StringType();
			} else if ($checkType === TypeParser::BOOL) {
				$narrowType[] = new Type\BooleanType();
			} else if ($checkType === TypeParser::CALLABLE) {
				$narrowType[] = new Type\CallableType();
			} else if ($checkType === TypeParser::ARRAY) {
				if (isset($parsedType['key']) && isset($parsedType['value'])) {
					$narrowType[] = new Type\ArrayType(self::narrowType($filename, $parsedType['key']), self::narrowType($filename, $parsedType['value']));
				} else {
					$narrowType[] = new Type\ArrayType(new Type\UnionType([new Type\IntegerType(), new Type\StringType()]), new Type\MixedType());
				}
			} else if ($checkType === TypeParser::LIST) {
				if (isset($parsedType['value'])) {
					$narrowType[] = Type\TypeCombinator::intersect(
						new Type\ArrayType(new Type\IntegerType(), self::narrowType($filename, $parsedType['value'])),
						new Type\Accessory\AccessoryArrayListType(),
					);
				} else {
					$narrowType[] = Type\TypeCombinator::intersect(
						new Type\ArrayType(new Type\IntegerType(), new Type\MixedType()),
						new Type\Accessory\AccessoryArrayListType(),
					);
				}
			} else if ($checkType === TypeParser::OBJECT) {
				if (isset($parsedType['class'])) {
					$narrowType[] = new Type\ObjectType($parsedType['class']);
				} else {
					$narrowType[] = new Type\ObjectType(\stdClass::class);
				}
			} else {
				throw new ShouldNotHappenException(sprintf('Invalid type to check \'%s\'.', $checkType));
			}
		}

		return count($narrowType) === 1 ? $narrowType[0] : new Type\UnionType($narrowType);
	}


	public function setTypeSpecifier(Analyser\TypeSpecifier $typeSpecifier): void
	{
		$this->typeSpecifier = $typeSpecifier;
	}

}
