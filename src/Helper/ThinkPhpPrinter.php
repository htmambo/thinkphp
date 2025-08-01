<?php declare(strict_types=1);
namespace Think\Helper;


use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard;

class ThinkPhpPrinter extends Standard
{
    var bool $skip = false;
    private $trans = 'md5';
    public function __construct(array $options = []) {
        parent::__construct($options);
        if(isset($options['translator'])) {
            $this->trans = $options['translator'];
        }
    }

    /**
     * Pretty prints an array of nodes and implodes the printed values.
     *
     * @param Node[] $nodes Array of Nodes to be printed
     * @param string $glue Character to implode with
     *
     * @return string Imploded pretty printed nodes> $pre
     */
    protected function pImplode(array $nodes, string $glue = ''): string {
        $pNodes = [];
        foreach ($nodes as $node) {
            if (null === $node) {
                $pNodes[] = '';
            } else {
                $result = $this->parseThink($this->p($node));
                if(is_array($result)) {
                    $result = $this->processThinkLang($result);
                }
                $pNodes[] = $result;
            }
        }

        return implode($glue, $pNodes);
    }

    protected function pArg(Node\Arg $node): string {
        $right = $this->p($node->value);
        $right = $this->processThinkLang($right);
        return ($node->name ? $node->name->toString() . ': ' : '')
               . ($node->byRef ? '&' : '') . ($node->unpack ? '...' : '')
               . $right;
    }

    // 这里做一个陷阱，如果是普通的字符串则直接返回，否则返回数组以供后续处理
    protected function pExpr_BinaryOp_Concat(BinaryOp\Concat $node, int $precedence, int $lhsPrecedence):string {
        static $ptimes = 0;
        $origResult = $this->pInfixOp(BinaryOp\Concat::class, $node->left, ' . ', $node->right, $precedence, $lhsPrecedence);
        $ltype = $node->left->getType();
        $rtype = $node->right->getType();
        $left = $this->_pInfixOp(BinaryOp\Concat::class, $node->left);
        $right = $this->_pInfixOp(BinaryOp\Concat::class, $node->right);
        if($ltype == 'Scalar_String' && $rtype == 'Scalar_String') {
            $lchr = substr($left, -1);
            $rchr = substr($right, 0, 1);
            if($lchr != $rchr) {
                $tmp = substr($right, 1, -1);
                $right = $lchr . str_replace($lchr, '\\' . $lchr, $tmp) . $lchr;
            }
            return substr_replace($left, substr($right, 1 , -1), -1, 0);
        }

        $left = $this->parseThink($left);
        $right = $this->parseThink($right);
        $result = [
            'source' => $left,
            'content' => $left,
            'vars' => []
        ];
        if($ltype == 'Scalar_String' || $ltype == 'Expr_BinaryOp_Concat') {
            if(is_array($left)) {
                $result = $left;
            }
        } else {
            $ptimes ++;
            $result['content'] = '\'{$var_' . $ptimes . '}\'';
            $result['vars']['var_' . $ptimes] = $left;
            $result['source'] = $left;
        }

        if($rtype == 'Scalar_String' || $rtype == 'Expr_BinaryOp_Concat') {
            if(is_string($right)) {
                $right = [
                    'content' => $right,
                    'source' => $right,
                    'vars' => []
                ];
            }
            $result['source'] .= ' . ' . $right['source'];
            $lchr = substr($result['content'], -1);
            $rchr = substr($right['content'], 0, 1);
            if($lchr != $rchr) {
                $tmp = substr($right['content'], 1, -1);
                $right['content'] = $lchr . str_replace($lchr, '\\' . $lchr, $tmp) . $lchr;
            }
            $result['content'] = substr_replace($result['content'], substr($right['content'], 1 , -1), -1, 0);
            $result['vars'] = array_merge($result['vars'], $right['vars']);
        } else {
            $ptimes ++;
            $result['content'] = substr_replace($result['content'], '{$var_' . $ptimes . '}', -1, 0);
            $result['source'] .= ' . ' . $right;
            $result['vars']['var_' . $ptimes] = $right;
        }
        if(is_array($result)) {
            $result = 'THINK:' . json_encode($result);
        }
        return $result;
    }

    protected function pExpr_FuncCall(Expr\FuncCall $node): string {
        $this->skip = $node->name == 'L';
        $result = $this->pCallLhs($node->name)
                  . '(' . $this->pMaybeMultiline($node->args) . ')';
        $this->skip = false;
        return $result;
    }

    protected function pExpr_MethodCall(Expr\MethodCall $node): string {
        // 这些调用需要原样返回
        $skips = [
            '$this->check_action_priv',
            '$adminPrivModel->check_action_priv',
            '$adminPrivModel->isPrivname'
        ];
        $chk = $this->pDereferenceLhs($node->var) . '->' . $this->pObjectProperty($node->name);
        $this->skip = in_array($chk, $skips);
        if(!$this->skip && $this->pObjectProperty($node->name) == 'check_action_priv') {
            print_r($node);
            echo $chk . PHP_EOL;
            exit;
        }
        $result  = $this->pDereferenceLhs($node->var) . '->' . $this->pObjectProperty($node->name)
                   . '(' . $this->pMaybeMultiline($node->args) . ')';
        $this->skip = false;
        return $result;
    }

    protected function pExpr_StaticCall(Expr\StaticCall $node): string {
        // 一些写文件方法
        if($this->p($node->name) == 'write') {
            $skips = [
                'Log', '\\Think\\Log'
            ];
            $this->skip = in_array($this->pDereferenceLhs($node->class), $skips);
            if(!$this->skip) {
                print_r($node);
                print_r($this);
                echo 'class:' . $this->pDereferenceLhs($node->class) . PHP_EOL;
                echo 'method:' . $node->name . PHP_EOL;
                echo 'error';
                exit;
            }
        }
        $result = $this->pStaticDereferenceLhs($node->class) . '::'
                  . ($node->name instanceof Expr
                ? ($node->name instanceof Expr\Variable
                    ? $this->p($node->name)
                    : '{' . $this->p($node->name) . '}')
                : $node->name)
                  . '(' . $this->pMaybeMultiline($node->args) . ')';
        $this->skip = false;
        return $result;
    }

    protected function pExpr_Eval(Expr\Eval_ $node): string {
        return 'eval(' . $this->processThinkLang($this->p($node->expr)) . ')';
    }

    protected function pExpr_Include(Expr\Include_ $node, int $precedence, int $lhsPrecedence): string {
        static $map = [
            Expr\Include_::TYPE_INCLUDE      => 'include',
            Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
            Expr\Include_::TYPE_REQUIRE      => 'require',
            Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
        ];
        $result = $this->processThinkLang($this->p($node->expr));
        return $map[$node->type] . ' ' . $result;
        static $map = [
            Expr\Include_::TYPE_INCLUDE      => 'include',
            Expr\Include_::TYPE_INCLUDE_ONCE => 'include_once',
            Expr\Include_::TYPE_REQUIRE      => 'require',
            Expr\Include_::TYPE_REQUIRE_ONCE => 'require_once',
        ];
        return $this->pPrefixOp(Expr\Include_::class, $map[$node->type] . ' ', $node->expr, $precedence, $lhsPrecedence);
    }

    // Other

    protected function pArrayItem(Node\ArrayItem $node): string {
        $this->skip = true;
        $key = (null !== $node->key ? $this->p($node->key) . ' => ' : '');
        $this->skip = false;
        $value = $this->p($node->value);
        $value = $this->processThinkLang($value);
        return $key
               . ($node->byRef ? '&' : '')
               . ($node->unpack ? '...' : '')
               . $value;
        return $this->pKey($node->key)
               . ($node->byRef ? '&' : '')
               . ($node->unpack ? '...' : '')
               . $this->p($node->value);
    }

    protected function pExpr_ArrayDimFetch(Expr\ArrayDimFetch $node): string {
        $result = '';
        if(null !== $node->dim) {
            $this->skip = true;
            $result = $this->processThinkLang($this->p($node->dim));
            $this->skip = false;
        }
        return $this->pDereferenceLhs($node->var)
               . '[' . (null !== $node->dim ? $result : '') . ']';
        return $this->pDereferenceLhs($node->var)
               . '[' . (null !== $node->dim ? $this->p($node->dim) : '') . ']';
    }

    protected function pExpr_Ternary(Expr\Ternary $node, int $precedence, int $lhsPrecedence): string {
        // a bit of cheating: we treat the ternary as a binary op where the ?...: part is the operator.
        // this is okay because the part between ? and : never needs parentheses.
        $result = '';
        if(null !== $node->if) {
            $result = $this->processThinkLang($this->p($node->if));
        }
        return $this->pInfixOp(Expr\Ternary::class,
                               $node->cond, ' ?' . (null !== $node->if ? ' ' . $result . ' ' : '') . ': ', $node->else,
                               $precedence, $lhsPrecedence
        );
    }

    protected function pExpr_Exit(Expr\Exit_ $node): string {
        $kind = $node->getAttribute('kind', Expr\Exit_::KIND_DIE);
        return ($kind === Expr\Exit_::KIND_EXIT ? 'exit' : 'die')
               . (null !== $node->expr ? '(' . $this->processThinkLang($this->p($node->expr)) . ')' : '');
    }

    // Declarations

    // Control flow

    protected function pStmt_Return(Stmt\Return_ $node): string {
        $result = '';
        if(null !== $node->expr) {
            $result = $this->processThinkLang($this->p($node->expr));
        }
        return 'return' . (null !== $node->expr ? ' ' . $result : '') . ';';
        return 'return' . (null !== $node->expr ? ' ' . $this->p($node->expr) : '') . ';';
    }

    // Other

    // Helpers

    /**
     * Pretty-print an infix operation while taking precedence into account.
     * 需要修改PrettyPrinterAbstract中pPrec和p的返回值类型，不能为String
     *
     * @param string $class          Node class of operator
     * @param Node   $leftNode       Left-hand side node
     * @param string $operatorString String representation of the operator
     * @param Node   $rightNode      Right-hand side node
     *
     * @return string Pretty printed infix operation
     */
    protected function pInfixOp(string $class, Node $leftNode, string $operatorString, Node $rightNode,
                                int $precedence, int $lhsPrecedence): string {
        list($opPrecedence, $newPrecedenceLHS, $newPrecedenceRHS) = $this->precedenceMap[$class];

        $right = $this->p($rightNode, $newPrecedenceLHS, $newPrecedenceRHS);
        $right = $this->parseThink($right);
        if(is_array($right) || $rightNode->getType() == 'Scalar_String') {
            $right = $this->processThinkLang($right);
        }
        if(is_array($right)) {
            echo PHP_EOL;
            print_r($right);
            echo PHP_EOL;
            exit;
        }
        $prefix = '';
        $suffix = '';
        if ($opPrecedence >= $precedence) {
            $prefix = '(';
            $suffix = ')';
            $lhsPrecedence = self::MAX_PRECEDENCE;
        }
        $left = $this->p($leftNode, $newPrecedenceLHS, $newPrecedenceLHS);
        $left = $this->parseThink($left);
        if(is_array($left)) {
            $left = $this->processThinkLang($left);
        }
        return $prefix . $left . $operatorString . $right . $suffix;
    }

    private function parseThink($right) {
        if(!$right) return $right;
        if(is_string($right) && substr($right, 0, 6) === 'THINK:') {
            $right = substr($right, 6);
            $right = json_decode($right, true);
        }
        return $right;
    }
    function processThinkLang($str, $value = []) {
        global $lang;
        if(!isset($lang)) $lang = [];
        $str = $this->parseThink($str);
        $source = $str;
        if(is_array($str)) {
            $value = $str['vars'];
            $source = $str['source'];
            $str = $str['content'];
        }
        if(is_array($str)) {
            //还是数组
            print_r($source);exit;
            return $source;
        }
        if($this->skip) {
            return $source;
        }
        // 还是有遗漏
        if(preg_match('@L\s*\([^\)]+\)@', $str)) {
            return $source;
        }
        if($str == '主编') {
            pre($this);exit;
        }
        if(is_string($str)) {
            if (
                substr($str, 0, 6) == 'array('
                ||
                (substr($str, 0, 1) == '[' && substr($str, -1, 1) == ']')
            ) {
                // $this->showTrace($str);
                // 不用折腾了
                return $str;
            }
            if(stristr($str, "'title'")) {
                //                $this->showTrace($str);
            }
        }
        if(preg_match('@(\p{Han}){1,}@u', $str)) {
            //有中文
            $vars = [];
            if($value) {
                $tmp = $value;
                if(count($value)>1) {
                    //去重
                    $tmp = array_unique($value);
                    if(count($tmp)!=count($value)) {
                        //有相同的值
                        foreach($value as $k => $v) {
                            $sk = array_search($v, $tmp);
                            if($sk == $k) continue;
                            $str = str_replace('{$' . $k . '}', '{$' . $sk . '}', $str);
                        }
                    }
                }
                $i = 0;
                //键值整理
                foreach($tmp as $k => $v) {
                    $kk = 'var_' . $i;
                    $i++;
                    if($kk != $k) {
                        $str = str_replace('{$' . $k . '}', '{$' . $kk . '}', $str);
                    }
                    $vars[] = "'" . $kk . "' => " . $v;
                }
            }
            $tmp = $str;
            if(
                in_array(substr($str, 0, 1), ['"', "'"])
                &&
                in_array(substr($str, -1, 1), ['"', "'"])
            ) {
                $tmp = substr($str, 1, -1);
            }
            //检查语言包中是否已经定义了相应的字符串
            if(!$key = array_search($tmp, $lang)) {
                if(is_object($this->trans) && method_exists($this->trans, 'trans')) {
                    $key = call_user_func([$this->trans, 'trans'], $tmp);
                } else {
                    $key = md5($tmp);
                }
                if(!isset($lang[$key])) {
                    $lang[$key] = $tmp;
                }
                $str = $key;
            }
            $str = "'" . addslashes($key) . "'";
            if($value) {
                $str = 'L(' . $str . ', [' .implode(', ', $vars). '])';
            } else {
                $str = 'L(' . $str . ')';
            }
        } else {
            $str = $source;
        }
        return $str;
    }

    protected function _pInfixOp(string $class, Node $Node) {
        //list($precedence, $associativity) = $this->precedenceMap[$class];
        //return $this->p($Node, $precedence, $associativity, -1);

        list($opPrecedence, $newPrecedenceLHS, $newPrecedenceRHS) = $this->precedenceMap[$class];
        $result = $this->p($Node, $newPrecedenceLHS, $newPrecedenceRHS);
        return $result;
    }

    /**
     * Pretty-print a prefix operation while taking precedence into account.
     *
     * @param string $class Node class of operator
     * @param string $operatorString String representation of the operator
     * @param Node $node Node
     * @param int $precedence Precedence of parent operator
     * @param int $lhsPrecedence Precedence for unary operator on LHS of binary operator
     *
     * @return string Pretty printed prefix operation
     */
    protected function pPrefixOp(string $class, string $operatorString, Node $node, int $precedence, int $lhsPrecedence): string {
        $opPrecedence = $this->precedenceMap[$class][0];
        $prefix = '';
        $suffix = '';
        if ($opPrecedence >= $lhsPrecedence) {
            $prefix = '(';
            $suffix = ')';
            $lhsPrecedence = self::MAX_PRECEDENCE;
        }
        $printedArg = $this->p($node, $opPrecedence, $lhsPrecedence);
        $printedArg = $this->processThinkLang($printedArg);
        if (($operatorString === '+' && $printedArg[0] === '+') ||
            ($operatorString === '-' && $printedArg[0] === '-')
        ) {
            // Avoid printing +(+$a) as ++$a and similar.
            $printedArg = '(' . $printedArg . ')';
        }
        return $prefix . $operatorString . $printedArg . $suffix;
    }


    private function showTrace($tips = '')
    {
        global $lang;
        pre($lang);
        pre($tips);
        $traces = debug_backtrace();
        foreach($traces as $trace) {
            echo $trace['file'] . ' at ' . $trace['line'];
            echo PHP_EOL;
        }
        exit;
    }

}