<?php declare(strict_types=1);

namespace Rector\CodingStyle\Rector\Catch_;

use PhpParser\Node;
use PhpParser\Node\Stmt\Catch_;
use Rector\CodingStyle\Naming\ClassNaming;
use Rector\PhpParser\NodeTraverser\CallableNodeTraverser;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class CatchExceptionNameMatchingTypeRector extends AbstractRector
{
    /**
     * @var ClassNaming
     */
    private $classNaming;

    /**
     * @var CallableNodeTraverser
     */
    private $callableNodeTraverser;

    public function __construct(ClassNaming $classNaming, CallableNodeTraverser $callableNodeTraverser)
    {
        $this->classNaming = $classNaming;
        $this->callableNodeTraverser = $callableNodeTraverser;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Type and name of catch exception should match', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        try {
            // ...
        } catch (SomeException $typoException) {
            $typoException->getMessage();
        }
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        try {
            // ...
        } catch (SomeException $someException) {
            $someException->getMessage();
        }
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Catch_::class];
    }

    /**
     * @param Catch_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if (count($node->types) !== 1) {
            return null;
        }

        $type = $node->types[0];
        $typeShortName = $this->classNaming->getShortName($type);

        $oldVariableName = $this->getName($node->var);
        if (! $oldVariableName) {
            return null;
        }

        $newVariableName = lcfirst($typeShortName);
        if ($oldVariableName === $newVariableName) {
            return null;
        }

        $node->var->name = $newVariableName;

        $this->renameVariableInStmts($node, $oldVariableName, $newVariableName);

        return $node;
    }

    private function renameVariableInStmts(
        Node\Stmt\Catch_ $catch,
        string $oldVariableName,
        string $newVariableName
    ): void {
        $this->callableNodeTraverser->traverseNodesWithCallable($catch->stmts, function (Node $node) use (
            $oldVariableName,
            $newVariableName
        ): void {
            if (! $node instanceof Node\Expr\Variable) {
                return;
            }

            if (! $this->isName($node, $oldVariableName)) {
                return;
            }

            $node->name = $newVariableName;
        });
    }
}