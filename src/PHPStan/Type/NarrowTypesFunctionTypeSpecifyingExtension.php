<?php declare(strict_types=1);

namespace Forrest79\PHPStan\Type;

use PHPStan\Analyser;
use PHPStan\Reflection;
use PHPStan\Type;
use PhpParser\Node;

final class NarrowTypesFunctionTypeSpecifyingExtension extends NarrowTypesReturnTypeExtension implements Type\FunctionTypeSpecifyingExtension
{

	public function isFunctionSupported(
		Reflection\FunctionReflection $functionReflection,
		Node\Expr\FuncCall $node,
		Analyser\TypeSpecifierContext $context,
	): bool
	{
		return self::isSupported($functionReflection->getName(), count($node->getArgs()));
	}


	public function specifyTypes(
		Reflection\FunctionReflection $functionReflection,
		Node\Expr\FuncCall $node,
		Analyser\Scope $scope,
		Analyser\TypeSpecifierContext $context,
	): Analyser\SpecifiedTypes
	{
		return $this->narrowTypes($functionReflection->getName(), $node->getArgs(), $scope);
	}


	protected static function getIsTypeName(): string
	{
		return 'is_type';
	}

}
