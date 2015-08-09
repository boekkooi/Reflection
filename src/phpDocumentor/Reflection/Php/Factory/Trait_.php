<?php
/**
 * This file is part of phpDocumentor.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright 2010-2015 Mike van Riel<mike@phpdoc.org>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://phpdoc.org
 */

namespace phpDocumentor\Reflection\Php\Factory;

use InvalidArgumentException;
use phpDocumentor\Reflection\Element;
use phpDocumentor\Reflection\Fqsen;
use phpDocumentor\Reflection\Php\ProjectFactoryStrategy;
use phpDocumentor\Reflection\Php\StrategyContainer;
use phpDocumentor\Reflection\Php\Trait_ as TraitElement;
use phpDocumentor\Reflection\Types\Context;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property as PropertyNode;
use PhpParser\Node\Stmt\Trait_ as TraitNode;
use PhpParser\Node\Stmt\TraitUse;

final class Trait_ implements ProjectFactoryStrategy
{
    /**
     * Returns true when the strategy is able to handle the object.
     *
     * @param TraitNode $object object to check.
     * @return boolean
     */
    public function matches($object)
    {
        return $object instanceof TraitNode;
    }

    /**
     * Creates an TraitElement out of the given object.
     * Since an object might contain other objects that need to be converted the $factory is passed so it can be
     * used to create nested Elements.
     *
     * @param TraitNode $object object to convert to an TraitElement
     * @param StrategyContainer $strategies used to convert nested objects.
     * @param Context $context
     * @return TraitElement
     */
    public function create($object, StrategyContainer $strategies, Context $context = null)
    {
        if (!$this->matches($object)) {
            throw new InvalidArgumentException(
                sprintf('%s cannot handle objects with the type %s',
                    __CLASS__,
                    is_object($object) ? get_class($object) : gettype($object)
                )
            );
        }

        $docBlock = $this->createDocBlock($object->getDocComment(), $strategies, $context);

        $trait = new TraitElement($object->fqsen, $docBlock);

        if (isset($object->stmts)) {
            foreach ($object->stmts as $stmt) {
                switch (get_class($stmt)) {
                    case PropertyNode::class:
                        $properties = new PropertyIterator($stmt);
                        foreach ($properties as $property) {
                            $element = $this->createMember($property, $strategies, $context);
                            $trait->addProperty($element);
                        }
                        break;
                    case ClassMethod::class:
                        $method = $this->createMember($stmt, $strategies, $context);
                        $trait->addMethod($method);
                        break;
                    case TraitUse::class:
                        foreach ($stmt->traits as $use) {
                            $trait->addUsedTrait(new Fqsen('\\'. $use->toString()));
                        }
                        break;
                }
            }
        }

        return $trait;
    }

    /**
     * @param Node|PropertyIterator|Doc $stmt
     * @param StrategyContainer $strategies
     * @param Context $context
     * @return Element
     */
    private function createMember($stmt, StrategyContainer $strategies, Context $context = null)
    {
        $strategy = $strategies->findMatching($stmt);
        return $strategy->create($stmt, $strategies, $context);
    }

    /**
     * @param Doc $docBlock
     * @param StrategyContainer $strategies
     * @param Context $context
     * @return null|\phpDocumentor\Reflection\DocBlock
     */
    private function createDocBlock(Doc $docBlock = null, StrategyContainer $strategies, Context $context = null)
    {
        if ($docBlock === null) {
            return null;
        }

        return $this->createMember($docBlock, $strategies, $context);
    }
}