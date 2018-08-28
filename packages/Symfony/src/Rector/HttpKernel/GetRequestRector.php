<?php declare(strict_types=1);

namespace Rector\Symfony\Rector\HttpKernel;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Node\NodeFactory;
use Rector\NodeAnalyzer\MethodCallAnalyzer;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;
use Rector\Symfony\Bridge\NodeAnalyzer\ControllerMethodAnalyzer;
use Rector\Utils\BetterNodeFinder;

final class GetRequestRector extends AbstractRector
{
    /**
     * @var ControllerMethodAnalyzer
     */
    private $controllerMethodAnalyzer;

    /**
     * @var NodeFactory
     */
    private $nodeFactory;

    /**
     * @var MethodCallAnalyzer
     */
    private $methodCallAnalyzer;

    /**
     * @var BetterNodeFinder
     */
    private $betterNodeFinder;

    public function __construct(
        ControllerMethodAnalyzer $controllerMethodAnalyzer,
        MethodCallAnalyzer $methodCallAnalyzer,
        NodeFactory $nodeFactory,
        BetterNodeFinder $betterNodeFinder
    ) {
        $this->controllerMethodAnalyzer = $controllerMethodAnalyzer;
        $this->nodeFactory = $nodeFactory;
        $this->methodCallAnalyzer = $methodCallAnalyzer;
        $this->betterNodeFinder = $betterNodeFinder;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Turns fetching of dependencies via `$this->get()` to constructor injection in Command and Controller in Symfony',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class SomeController
{
    public function someAction()
    {
        $this->getRequest()->...();
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
use Symfony\Component\HttpFoundation\Request;

class SomeController
{
    public action(Request $request)
    {
        $request->...();
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class, MethodCall::class];
    }

    /**
     * @param ClassMethod|MethodCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($this->isGetRequestInAction($node)) {
            return $this->nodeFactory->createVariable('request');
        }

        if ($this->isActionWithGetRequestInBody($node)) {
            $requestParam = $this->nodeFactory->createParam('request', 'Symfony\Component\HttpFoundation\Request');

            $node->params[] = $requestParam;

            return $node;
        }

        return null;
    }

    private function isActionWithGetRequestInBody(Node $node): bool
    {
        if (! $this->controllerMethodAnalyzer->isAction($node)) {
            return false;
        }

        return (bool) $this->betterNodeFinder->find($node, function (Node $node) {
            return $this->methodCallAnalyzer->isMethod($node, 'getRequest');
        });
    }

    private function isGetRequestInAction(Node $node): bool
    {
        if (! $this->methodCallAnalyzer->isMethod($node, 'getRequest')) {
            return false;
        }

        $methodNode = $node->getAttribute(Attribute::METHOD_NODE);

        return $this->controllerMethodAnalyzer->isAction($methodNode);
    }
}