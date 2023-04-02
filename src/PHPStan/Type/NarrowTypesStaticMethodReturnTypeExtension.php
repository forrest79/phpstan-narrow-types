<?php declare(strict_types=1);

namespace Forrest79\PHPStan\Type;

use Forrest79\NarrowTypes;
use PHPStan\Analyser;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type;
use PhpParser\Node\Expr\StaticCall;

final class NarrowTypesStaticMethodReturnTypeExtension extends NarrowTypesReturnTypeExtension implements Type\StaticMethodTypeSpecifyingExtension
{

	public function getClass(): string
	{
		return NarrowTypes::class;
	}


	public function isStaticMethodSupported(
		MethodReflection $staticMethodReflection,
		StaticCall $node,
		Analyser\TypeSpecifierContext $context,
	): bool
	{
		return self::isSupported($staticMethodReflection->getName(), count($node->getArgs()));
	}


	public function specifyTypes(
		MethodReflection $staticMethodReflection,
		StaticCall $node,
		Scope $scope,
		Analyser\TypeSpecifierContext $context,
	): Analyser\SpecifiedTypes
	{
		return $this->narrowTypes($staticMethodReflection->getName(), $node->getArgs(), $scope);
	}


	protected static function getIsTypeName(): string
	{
		return 'isType';
	}

}
