<?php
// @author: C.A.D. BONDJE DOUE
// @file: FormatPHPCode.php
// @date: 20250705 15:53:31
namespace igk\tools\Reflector;
use Error;
use Exception;
use IGK\System\Console\Logger;
use IGK\System\Exceptions\CssParserException;
use IGK\System\Exceptions\ArgumentTypeNotValidException;
use IGK\System\Text\ITextCodeFormatter;
use IGK\System\Text\RegexDetectBuffer;
use IGK\System\Text\RegexDetectHandler;
use IGK\System\Text\RegexMatcherContainer;
use IGK\System\Text\RegexMatcherUtility;
use IGKException;
use ReflectionException;
/**
 * 
 * @package igk\tools\Reflector
 * @author C.A.D. BONDJE DOUE
 */
class FormatPHPCode implements ITextCodeFormatter
{
    var $blockOnly;
    /**
     * base depth 
     * @var mixed
     */
    var $baseDepth = 0;
    var $tabStop;
    public function __construct()
    {
        $this->tabStop = str_repeat(' ', 4);
    }
    /**
     * remove block def
     * @param mixed $s 
     * @return string 
     */
    protected function removeBlockDef($s)
    {
        while ($s = trim($s)) {
            $ln = strlen($s);
            if (($ln > 1) && ($s[0] == '{') && ($s[$ln - 1] == '}')) {
                $s = substr($s, 1, $ln - 2);
                continue;
            }
            break;
        }
        return $s;
    }
    /**
     * 
     * @param string $def 
     * @return bool 
     */
    public function isEmptyBlock(string $def): bool
    {
        $s = $this->removeBlockDef($def);
        return empty($s);
    }
    public function initialize(RegexMatcherContainer $ctn)
    {
        $doc_comment = $ctn->appendCommentDocBlock()->last();
        $comment = $ctn->appendSingleLineComment()->last();
        $mcomment = $ctn->appendMultilineComment()->last();
        $string = $ctn->appendStringDetection()->last();
        $curl_brank = $ctn->begin('\{', '\}', 'curl-brank')->last();
        $parenthese_brank = $ctn->begin('\(\\s*', '\\s*\)', 'parenthese-brank')->last();
        $array_brank = $ctn->begin('\[', '\]', 'array-brank')->last();
        $space = $ctn->match(' {2,}', 'multispace')->last();
        $empty_line = $ctn->match('^\\s*?\\n', 'empty-line')->last();
        $instruction = $ctn->match('\\s*;\\s*', 'end-instruction')->last();
        $dyn_operator = $ctn->begin('\\s*->\\s*\{', '\}', 'dynamic-operator')->last();
        $unary_operator = $ctn->match('\\s*(\!|\+\+|--|\+=|-=|\.=)\\s*', 'unary_operator')->last();
        $operator = $ctn->match('\\s*((=|<|>)?=|<=>|\|\||&&|\?\?)\\s*', 'operator')->last();
        $assoc_operator = $ctn->match('\\s*(=>)\\s*', 'assoc_operator')->last();
        $concatenation_operator = $ctn->match('\\s*(\.)\\s*', 'concatenation_operator')->last();
        $ref_operator = $ctn->match('\\s*(->)\\s*', 'ref-operator')->last();
        $arg_separator = $ctn->match('\\s*,\\s*', 'arg-separator')->last();
        $static_separator = $ctn->match('\\s*::\\s*', 'static-separator')->last();
        $self_instance_operator = $ctn->match('\\$this\\b', 'self-instance-operator')->last();
        $reserved_words = $ctn->match('\\b(as|break|case|continue|default|do|else|elseif|exit|endif|endwhile|enddo|endforeach|false|final|for|foreach|function|global|if|instanceof|int|interface|namespace|null|parent|private|protected|return|self|static|static|string|switch|trait|true|use|var|while|yield)\\b', 'reserver-word')->last();
        $method_invoke = $ctn->match('\\b([a-zA-Z_][a-zA-Z0-9_])\\s*(?=\()', 'method-invocation')->last();
        $here_doc = [];
        $ctn->autoStore = false;
        RegexMatcherUtility::AppendPhpHereDoc($ctn, $here_doc);
        $ctn->autoStore = true;
        $ctn->append($ctn->createPattern(['patterns' => $here_doc]));

        $dyn_operator->patterns = [
            $comment,
            $string,
            ["begin"=>"\/\*", "end"=>"\*\/"],
            ["match"=>"\\s+", "tokenID"=>"dc-to-remove"]
        ];

        $curl_brank->patterns = [
            $comment,
            $doc_comment,
            $mcomment,
            $unary_operator,
            ['patterns' => $here_doc],
            $dyn_operator,
            $reserved_words,
            $parenthese_brank,
            $array_brank,
            $self_instance_operator,
            // $empty_line,
            $string,
            $space,
            $instruction,
            $arg_separator,
            $operator,
            $method_invoke,
            $ref_operator,
            $concatenation_operator,
            $static_separator,
            $curl_brank,
        ];
        $parenthese_brank->patterns = [
            $doc_comment,
            $string,
            $operator,
            $arg_separator,
            $space,
            $array_brank,
            $curl_brank,
            $unary_operator,
            $concatenation_operator,
            $static_separator,
            ['patterns' => $here_doc],
            $parenthese_brank,
        ];
        $array_brank->patterns = [
            $doc_comment,
            $string,
            $space,
            $self_instance_operator,
            $curl_brank,
            $assoc_operator,
            $concatenation_operator,
            $static_separator,
            ['patterns' => $here_doc],
            $array_brank,
        ];
    }
    /**
     * 
     * @param string $src 
     * @return string 
     * @throws IGKException 
     * @throws Exception 
     */
    public function format(string $src):string
    {
        $regex = new RegexMatcherContainer;
        $this->initialize($regex);
        $depth = $this->baseDepth;
        $info = (object)[
            'initTokens' => [],
            'replace' => [],
            'depth' => &$depth,
        ];
        $buffer = new RegexDetectBuffer;
        $buffer->source = $src;
        $handler = new RegexDetectHandler($regex);
        $fc = function ($e) use ($buffer,  $info) {
            igk_is_debug() && Logger::info("tokenID:" . $e->tokenID . "[" . $e->value . "]");
            switch ($e->tokenID) {
                case 'dc-to-remove':
                    $info->replace[] = [$e, ''];
                    break;
                case 'array-brank':
                    $info->replace[] = [$e, $e->value];
                    break;
                case 'curl-brank':
                    $c = $this->treat_curl_sub($info, $e);
                    $info->replace[] = [$e, $c];
                    $info->depth--;
                    break;
                case 'end-instruction':
                    $info->replace[] = [$e, ";\n", "rtrim"=>true];
                    break;
                case 'arg-separator':
                    $info->replace[] = [$e, trim($e->value) . " "];
                    break;
                case 'static_separator':
                    $info->replace[] = [$e, trim($e->value)];
                    break;
                case 'operator':
                    $tc = trim($e->value) ;
                    $info->replace[] = [$e, $tc=='='? $tc.' ': " " . $tc. " "];
                    break;
                case 'unary_operator':
                case 'ref_operator':
                case 'method_invoke':
                    $info->replace[] = [$e, trim($e->value)];
                    break;
                case 'multispace':
                    $info->replace[] = [$e, ' '];
                    break;
                case 'assoc_operator':
                    $info->replace[] = [$e, trim($e->value).' '];
                    break;
                case 'empty-line':
                    $info->replace[] = [$e, "\n"];
                    break;
                case 'comment-multiline':
                    $end = igk_str_endwith($e->value, "\n") ? "\n": "";
                     $info->replace[] = [
                        $e,
                        trim($e->value). $end,
                        'preserve' => true,
                        'tokenID' => $e->tokenID
                    ];
                    break;
                case 'comment-docbloc':
                    $def = $this->tab($info->depth);
                    $info->replace[] = [
                        $e,
                        "\n".$def. FormatStringBuilder::ClueDef(trim($e->value), $def ). "\n",
                        "rtrim"=>true,
                        'preserve' => true,
                        'tokenID' => $e->tokenID
                    ];
                    break;
                case 'here-doc':
                case 'string':
                    $info->replace[] = [$e, $e->value, 'preserve' => true];
                    break;
                case 'parenthese-brank':
                    $v_ps = $e->value;
                    $v_ps = '('.igk_str_rm_start($v_ps, $e->beginCaptures[0][0]);
                    $v_ps = igk_str_rm_last($v_ps, $e->endCaptures[0][0]).')'; 
                    $info->replace[] = [$e, $v_ps];
                    break;
            }
            $v_rp = &$info->replace;
            if ($e->parentInfo == null) {
                if ($v_rp) {
                    $cp = array_pop($v_rp);
                    if ($cp[0]->from == $e->from) {
                        if ($chain = self::GetChainUntil($v_rp, $e)) {
                            $cp[1] = '' . $this->replaceChain($chain,  $e->value, $e->from, $info->depth);
                        }
                        $v_rp[] = $cp;
                    } else {
                        igk_die('not a valid replacement list');
                    }
                    $buffer->depth =  $this->tab($info->depth); 
                    while (count($v_rp) > 0) {
                        $q = array_shift($v_rp);
                        list($ee, $ss) = $q;
                        if ($buffer->lineFeed){
                            $buffer->output .= "\n".$buffer->depth;
                        } else if ($buffer->isEmpty()){
                            $buffer->output .= $buffer->depth;
                            $buffer->lineFeed = true;
                        }
                        $buffer->replace($ee, $ss);
                    }
                }
            } else {
                if ($v_rp){
                    $cp = $v_rp[count($v_rp)-1];
                    if ($cp[0]->from == $e->from) {
                        $cp = array_pop($v_rp);
                        if ($chain = self::GetChainUntil($v_rp, $e)) {
                            $cp[1] = '' . $this->replaceChain($chain,  $e->value, $e->from, $info->depth);
                        }
                        $v_rp[] = $cp;
                    } 
                }
            }
        };
        // $handler->startTokenListener;
        $handler->itemTokenListener = function ($e) use ($info) {
            igk_is_debug() && Logger::warn('mark token:' . $e->tokenID);
            switch ($e->tokenID) {
                case 'curl-brank':
                    $info->depth++;
                    break;
            }
        };
        $handler->detect($src, $fc);
        return rtrim($buffer->output() ?? '');
    }
    /**
     * get chain until 
     * @param mixed &$v_plc 
     * @param mixed $e 
     * @return array 
     */
    public static function GetChainUntil(&$v_plc, $e)
    {
        return RegexMatcherUtility::GetChainUntil($v_plc, $e);       
    }
    /**
     * treat sub definition
     * @param mixed $info 
     * @param mixed $e 
     * @return string 
     * @throws Error 
     * @throws IGKException 
     * @throws Exception 
     * @throws CssParserException 
     * @throws ArgumentTypeNotValidException 
     * @throws ReflectionException 
     */
    protected function treat_curl_sub($info, $e)
    {
        $c = $e->value;
        $v_plc = &$info->replace;
        if ($v_plc) {
            $chain = [];
            while (($tc = count($v_plc)) > 0) {
                if ($v_plc[$tc - 1][0]->from < $e->from) {
                    break;
                }
                $r = array_pop($v_plc);
                array_unshift($chain, $r);
            }
            $v_chaining = true && ($chain);
            if ($chain) {
                $c = $this->replaceChain($chain, $c, $e->from, $info->depth);
            }
            if (empty($c = trim($this->removeBlockDef($c)))) {
                $c = '{}';
                return $c;
            } else if (!$v_chaining) {
                $c = $this->tab($info->depth) . $this->_clue_def($c, $this->tab($info->depth));
            } else {
                $c = $this->tab($info->depth) . ltrim($c);
            }
        } else {
            if (empty($c = trim($this->removeBlockDef($c)))) {
                $c = '{}';
                return $c;
            }
        }
        // $c = array_filter(array_map($this->glue($info->depth), explode("\n", $c)));
        $p =  implode("\n", ['{', $c, $this->tab(max(0, $info->depth - 1)) . '}']);
        return $p;
    }
    function treatReplaceChain(&$v_plc, $e, $info,  &$v_chaining)
    {
        $chain = [];
        $c = $e->value;
        while (($tc = count($v_plc)) > 0) {
            if ($v_plc[$tc - 1][0]->from < $e->from) {
                break;
            }
            $r = array_pop($v_plc);
            array_unshift($chain, $r);
        }
        $v_chaining = true && ($chain);
        if ($chain) {
            $c = $this->replaceChain($chain, $c, $e->from, $info->depth);
        }
        return $c;
    }
    /**
     * glue element but ignore empty line . ex
     * @param string $c 
     * @param string $depth 
     * @return string 
     */
    private function _clue_def(string $c, string $depth)
    {
        return FormatStringBuilder::ClueDef($c, $depth);
    }
    /**
     * replacement chain 
     * @param array $chain 
     * @param string $value 
     * @param int $from 
     * @param int $depth 
     * @return string 
     */
    protected function replaceChain(array $chain, string $value, int $from, int $depth)
    {
        $Tss = $value;
        $offset = $from;
        $v_fsb = new FormatStringBuilder;
        $v_fsb->depth = $depth;
        $v_fsb->tabStop = $this->tabStop;
       // $r = '';
        $k = 0;
        $v_d = $this->tab($depth);
        while (count($chain) > 0) {
            $q = array_shift($chain);
            list($tc, $ss) = $q;           
            $v_size = ($tc->from - $offset)-$k;
            if ($v_size<0){
                igk_die("invalid chain detection");
            }
            $before = substr($Tss, $k, $v_size);// - $offset) - $k);
            //format previous data
            $tr = explode("\n", $before);
            if (count($tr) > 1) {
                $before = $this->_clue_def($before, $v_d); 
            }
            // $r .= $before . $ss;
            igk_is_debug() &&  Logger::danger("before:[" . $before."]");
            $v_fsb->append($before);
            $v_fsb->lineFeed = $v_fsb->lineFeed || igk_str_endwith($before, "\n");
            if (igk_getv($q, "rtrim")){
                 $v_fsb->rtrim();
                 $v_fsb->lineFeed = false; 
            }
            $v_fsb->append($ss);
            $v_fsb->lineFeed = $v_fsb->lineFeed || igk_str_endwith($ss, "\n") || preg_match("/\}$/", rtrim($ss));
            $k = $tc->to - $offset;       
        }
        $v_fsb->append(substr($Tss, $k));
        return $v_fsb.'';
    }
    protected function glue()
    {
        return function ($a) {
            if (empty(trim($a))) {
                return null;
            }
            return ltrim($a);
        };
    }
    /**
     * 
     * @param mixed $depth 
     * @return string 
     */
    protected function tab($depth)
    {
        $c = str_repeat($this->tabStop, $depth);
        return $c;
    }
}