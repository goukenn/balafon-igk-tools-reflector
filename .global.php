<?php
// @author: C.A.D. BONDJE DOUE
// @file: %modules%/igk/tools/Reflector/.global.php
// @date: 20250703 15:11:27
// + module entry file 
namespace igk\tools\Reflector;

use Closure;
use Exception;
use IGK\Helper\Activator;
use IGK\Helper\IO;
use IGK\System\Console\Logger;
use IGK\System\Text\ITextCodeFormatter;
use IGK\System\Text\RegexDetectBuffer;
use IGK\System\Text\RegexDetectHandler;
use IGK\System\Text\RegexDetectInfo;
use IGK\System\Text\RegexMatcherCapture;
use IGK\System\Text\RegexMatcherContainer;
use IGK\System\Text\RegexMatcherUtility;
use IGKException;

// brank store on definition mode so need to update selection 
// 
/**
 *  formatting code portion
 * */
function format(string $str)
{
    $s = '';
    $rg = new RegexMatcherContainer;
    $rg->match("\\s+", "space");
    $rg->begin("\/\*", "\*\/", "comment-mark");
    $buffer = new RegexDetectBuffer;
    $buffer->source = $str;
    (new RegexDetectHandler($rg))->detect($str, function ($e) use ($buffer) {
        switch ($e->tokenID) {
            case "space":
                $buffer->replace($e, ' ');
                break;
        }
    });
    $s = $buffer->output();
    return $s;
}
function format_comment(string $str, $d)
{
    return implode("\n", array_map(function ($a) use ($d) {
        return $d . trim($a);
    }, explode("\n", $str)));
}
function store_file($reflector, $file)
{
    if (!isset($reflector->files[$file])) {
        $reflector->files[$file] = [];
    }
}
/**
 * store definition to reflector
 * @param mixed $reflector 
 * @param string $file 
 * @param 'function'|'class'|'trait'|'interface' $type 
 * @param mixed $name 
 * @return bool|ReflectorDefinition 
 * @throws Exception 
 */
function store_def($reflector, $file, string $type, $name)
{
    store_file($reflector, $file);
    if (! isset($reflector->files[$file][$type])) {
        $reflector->files[$file][$type] = [];
    }
    if ($type == 'function') {
        if ($name instanceof DCMethodInfo) {
            $n = $name->name;
            if (isset($reflector->files[$file][$type][$n])){
                if (!is_array($reflector->files[$file][$type][$n])){
                    $reflector->files[$file][$type][$n] = [$reflector->files[$file][$type][$n]];
                }
                $cond = $name->conditional_condition;
                if ($name->conditional == 'else'){
                    $cond = '!'.$cond;
                }
                $reflector->files[$file][$type][$n][$cond] = $name;
            } else 
                $reflector->files[$file][$type][$n] = $name;
            return true;
        }
        return false;
    } else {
        $odef = new ReflectorDefinition($reflector);
        $odef->type = $type;
        $odef->name = $name;
        $odef->file = $file;
        $reflector->files[$file][$type][$name] = $odef;
        if ($ktype = igk_getv([
            'class' => 'classes',
            'interface' => 'interfaces',
            'trait' => 'traits',
        ], $type)) {
            $reflector->{$ktype}[$name] = $odef;
        }
        return $odef;
    }
}
/**
 * store condition .
 * @param mixed $info 
 * @param mixed $e 
 * @return void 
 */
function store_cond($info, $e)
{
    switch ($info->dcmode) {
        case 'method':
            $info->method->condition = $e->value;
            break;
        case 'dc-function-def':
            $info->function->condition = $e->value;
            break;
    }
}
function store_child_code($info, $e)
{
    switch ($info->dcmode) {
        case 'method':
            $info->method->code = $info->noCode ? null : $e->value;
            $info->dcmode = null;
            break;
        case 'use':
            sort($info->use_mark);
            $key = implode(',', $info->use_mark);
            $info->use_block[$key] = $e->value;
            $info->dcmode = null;
            $info->use_mark = [];
            break;
        case 'dc-function-def':
            $info->function->code = $info->noCode ? null : $e->value;
            $info->dcmode = null;
            $info->function = null;
            break;
    }
}
function identifier($e)
{
    $c = [];
    if ($e->tokenID) {
        $c[] = $e->tokenID;
    }
    $q = $e->parentInfo;
    while ($q) {
        if ($q->match->tokenID) {
            array_unshift($c, $q->match->tokenID);
        }
        $q = $q->parent;
    }
    return implode(',', $c);
}
/**
 * init begin end info
 * @param mixed $info 
 * @param mixed $e 
 * @param mixed $g 
 * @return void 
 */
function initialize($info, $e, $src, $pos = null)
{
    $v_rt = &$info->start_detect;
    $tg = igk_last($v_rt);
    $p = $e->parentInfo;
    $chain = [];
    $pos = $pos ?? $e->to;
    while ($tg && $p) {
        if ($p->pos == $tg->pos) {
            if ($chain) {
                while (count($chain) > 0) {
                    $q = array_shift($chain);
                    init_chainlist($info, $q, $src, $pos);
                }
            }
            break;
        } else {
            if ($p->pos < $tg->pos) {
                array_pop($v_rt);
                if (!($tg = igk_last($v_rt))) {
                    break;
                }
                continue;
            }
            array_unshift($chain, $p);
            $p = $p->parent;
        }
    }
}
/**
 * init chain list 
 */
function init_chainlist($info, $g, $src, $pos)
{
    $v_token_id = $g->match->tokenID;
    if (!isset($info->start_detect[$g->pos])) {
        $info->start_detect[$g->pos] = $g;
    } else {
        if ($info->start_detect[$g->pos] !== $g) {
            igk_die('not matching');
        }
        return;
    }
    igk_is_debug() && Logger::danger('start: ' . $v_token_id);

    if ($info->start) {
        if ($v_token_id == 'namespace-block') {
            $info->namespace = $g->captures[1][0];
        }
        if ($v_token_id != 'single-line-comment') {
            if (is_array($info->global_comments) && empty($info->global_comments)) {
                $info->global_comments = false;
            }
        }
    }
    if ($v_token_id == 'php-start') {
        $info->start = true;
    }
    if ($v_token_id == 'php-end') {
        $info->start = false;
    }
    if ($v_token_id == 'class-def') {
        $info->class_modifiers = $info->modifiers;
        $info->class_docBlock = $info->lastDocBlock;
        $info->lastDocBlock = null;
        $info->modifiers = [];
        $info->class_def = true;
    }

    if ($v_token_id == 'curl-brank') {
        if ($info->def_chain) {
            $info->def_chain->level++;
        }
        if ($info->var_type) {
            $info->curl_start = true;
        }
    }
    if ($info->buffered_filter) {
        $info->buffered_filter->start($g, $src, $pos);
    }
}
function init_treat()
{
    $here_doc = [];
    $regex = new RegexMatcherContainer;
    // init no registered 
    $regex->autoStore = false;

    // init here doc 
    RegexMatcherUtility::AppendPhpHereDoc($regex, $here_doc);
    $single_line_comment = $regex->match("\/\/.+", "single-line-comment")->last();
    $region = $regex->match("#.+", "region-comment")->last();
    $multiline_comment = $block_pattern[] = $regex->begin('\/\*', '\*\/', 'multiline-comment')->last();
    $php_doc = $regex->appendCommentDocBlock()->last();
    $string = $regex->appendStringDetection('string', true)->last();
    $end_instruct = $regex->match('\\s*;\\s*', 'end-instruction')->last();
    $global_operator = $regex->match('\\s*(&&|\|\||==)\\s*', 'gdc-operator')->last();
    $comments = $regex->createPattern(['patterns' => [$single_line_comment, $multiline_comment, $region]]);
    // + | definition expression
    // + | global 
    $gdc_litteral = $regex->match('([a-zA-Z_][a-zA-Z_0-9]*)', 'gdc-litteral')->last();
    $gdc_ns_litteral = $regex->match('(\\\\)?([a-zA-Z_][a-zA-Z_0-9]*)((\\\\([a-zA-Z_][a-zA-Z_0-9]*))+)?', 'gdc-litteral')->last();

    $implements = $regex->createPattern(['match' => '\\b(implements)\\b', 'tokenID' => 'dc-implements']);
    $extends = $regex->createPattern(['match' => '\\b(extends)\\b', 'tokenID' => 'dc-extends']);
    $use = $regex->createPattern(['match' => '\\buse\\b', 'tokenID' => 'dc-use']);
    $as = $regex->createPattern(['match' => '\\bas\\b\\s*([a-zA-Z_][a-zA-Z_]*)', 'tokenID' => 'dc-as']);

    $dc_func_detector = $regex->match('\\b(function)\\b(?:\\s*&\\s*)?', 'dc-function')->last();


    $var = $regex->createPattern(['match' => '(\\$[a-zA-Z_][a-zA-Z_]*)\\b', 'tokenID' => 'dc-variable']);
    $func_return_type  = $regex->begin(":", "(?=;|\{)", "dc-return-type")->last();
    $ns = $regex->match('namespace\\b\\s*([a-zA-Z_][a-zA-Z_]*(?:\\\\[a-zA-Z_][a-zA-Z_]*)*)\\s*;', 'namespace')->last();
    $modifier = $regex->createPattern(['match' => '\\b(private|public|protected|abstract|final|var|const|readonly)\\b', 'tokenID' => 'dc-modifier']);

    $ns_b = $regex->begin('namespace\\b\\s*([a-zA-Z_][a-zA-Z_]*(?:\\\\[a-zA-Z_][a-zA-Z_]*)*)\\s*(?=\{)', '(?<=\})', 'namespace-block')->last();
    $ns_b->patterns = [$regex->createPattern([
        'begin' => '\{',
        'end' => '\}',
        'tokenID' => 'namespace-curl-block',
        'patterns' => &$block_pattern
    ])];

    $reserved_words = $regex->match('\\b(as|break|case|continue|default|do|else|elseif|exit|endif|endwhile|endforeach|false|final|for|foreach|function|global|if|instanceof|int|interface|namespace|null|parent|private|protected|return|require|require_once|include|include_once|__FILE__|__DIR__|self|static|static|string|switch|trait|true|use|var|while|yield)\\b', 'reserver-word')->last();
    $global_condition_start = $regex->match("\\bif|else(if)?\\b", "gdc-condition-start")->last();
    $global_class_def = $regex->begin('\\b(interface|trait|class)\\b\\s*([a-zA-Z_][a-zA-Z_0-9]*)\\b', '(?<=\})', 'class-def')->last();
    $curl_brank_class_def = $regex->begin('\{', '\}', 'curl-brank')->last();
    $array = $regex->createPattern(['begin' => '\[', 'end' => '\]', 'tokenID' => 'dc-array']);
    $cond = $regex->createPattern(['begin' => '\(', 'end' => '\)', 'tokenID' => 'dc-cond']);
    $curl_brank = $regex->begin('\{', '\}', 'curl-brank')->last();
    $concat = $regex->createPattern(['match' => '\\.', 'tokenID' => 'dc-concat']);
    $value = $regex->createPattern(['begin' => '=', 'end' => ';', 'tokenID' => 'dc-value']);
    $global_use = $regex->begin('\\buse\\b', ';', 'global-use')->last();
    $type_def = $regex->match('(?:((\\b|(\\\))(?:[a-zA-Z_][a-zA-Z_0-9]*))(\\\(?:[a-zA-Z_][a-zA-Z_0-9]*))*)\\b', 'dc-typedef')->last();

    $use_anonymous = $regex->match('\\buse\\b\\s*', 'dc-use-anonymous')->last();
    $var_constant = $regex->match('\\b(?:((?:\\\\)?(?:[a-zA-Z_]+[a-zA-Z_]*)(?:(?:\\\\(?:[a-zA-Z_]+[a-zA-Z_]*))+)?)::)?([a-zA-Z_]+[a-zA-Z_]*)\\b', 'dc-var-constant')->last();

    $var_constant->captures = [
        "1" => [
            'name' => 'definition',
            'patterns' => [
                ['match' => '\\b(self|static)\\b', 'tokenID' => 'reserved-type']
            ]
        ]
    ];
    $var_constant->patterns = [];
    $value->patterns = [
        $comments,
        $string,
        $array,
        $cond,
        $curl_brank
    ];

    $cond->patterns = [
        $comments,
        $string,
        $var_constant,
        $var,
        $array,
        $cond,
    ];
    $curl_brank->patterns = [
        $php_doc,
        $comments,
        $string,
        $dc_func_detector,
        $use_anonymous,
        $end_instruct, // to handle global close
        $global_condition_start,
        $reserved_words,
        $gdc_litteral,
        $func_return_type,
        $var,
        $array,
        $cond,
        $curl_brank
    ];

    $global_class_def->patterns = [
        $php_doc,
        $comments,
        $implements,
        $extends,
        $type_def,
        $gdc_litteral,
        $curl_brank_class_def,
    ];
    $curl_brank_class_def->patterns = [
        $php_doc,
        $comments,
        $string,
        ['patterns' => $here_doc],
        $use,
        $modifier,
        $string,
        $end_instruct,
        $var,
        $cond,
        $dc_func_detector,
        $reserved_words,
        $gdc_litteral,
        $type_def,
        $func_return_type,
        $value,
        $curl_brank_class_def,
    ];
    $conditionals = [];
    $base_block = [
        $php_doc,
        $comments,
        $implements,
        $cond,
        $string,
        $reserved_words,
        $gdc_litteral,
        $value,
        $var,
    ];
    $cond_foreach = $regex->begin('foreach', '(?<=\})|(?<=endforeach)(?=\\s*;)', 'conditional-loop-foreach')->last();
    $cond_foreach->patterns = [
        ['patterns' => $base_block],
        ['patterns' => &$conditionals],
        $cond_foreach,
        $curl_brank,
        $regex->createPattern([
            "begin" => ":",
            "end" => ";",
            "tokenID" => "begin-cond-curl-brank",
            "patterns" => [
                ['match' => '(?<=endforeach)(?=\\s*;)', "name" => "close-loop"],
                ['patterns' => $base_block],
            ]
        ])
    ];
    // $cond_while = $regex->begin('while', '(?<=\}|endwhile\\s*;)', 'conditional-loop-while')->last();
    // $cond_if = $regex->begin('if', '(?<=\}|endif\\s*;)', 'conditional-if')->last();

    $conditionals = array_merge($conditionals, [
        $cond_foreach,
        // $cond_while,
        // $cond_if,
    ]);

    $regex->autoStore = true;
    // global definition 
    $regex->match('<\?php(\\s+|\\s*$)', 'php-start');
    $regex->match('\?>', 'php-end');
    $regex->append($regex->createPattern(['patterns' => $here_doc]));
    // $regex->append($regex->createPattern(['patterns' => $conditionals]));
    $regex->append($end_instruct);
    $regex->append($global_operator);
    $regex->append($single_line_comment);
    $regex->append($php_doc);
    $regex->append($multiline_comment);
    $regex->append($global_use);
    $regex->append($string);
    $regex->append($implements);
    $regex->append($extends);
    $regex->append($as);
    $regex->append($use);
    $regex->append($var);
    $regex->append($ns_b);
    $regex->append($ns);
    $regex->append($global_condition_start);
    $regex->append($global_class_def);
    $regex->append($dc_func_detector);
    $regex->append($func_return_type);
    $regex->append($modifier);
    $regex->append($reserved_words);
    $regex->append($gdc_ns_litteral);
    $regex->append($gdc_litteral);
    $regex->append($concat);
    $regex->append($value);
    $regex->append($cond);
    $regex->append($array);
    $regex->append($curl_brank);
    // |  
    // igk_wln('file: @', __FILE__.":".__LINE__ );
    // $c = new RegexMatcherContainerTmLanguageConverter;
    // igk_io_w2file('/tmp/convert.json', serialize($regex)); 
    // exit;

    $s = new RegexDetectHandler($regex);
    $regex->resetTreatment();
    // $regex->captureHandlerListener = function($v, $mark, $e){
    //     return $v.'<:::>';
    // };
    $reflector = Activator::CreateNewInstance(ReflectorContainer::class, (object)[
        'interfaces' => [],
        'classes' => [],
        'functions' => [],
        'traits' => [],
        'files' => [],
        'global_uses' => []
    ]);
    return compact('reflector', 's');
}
/**
 * directory or file 
 * @param string $input 
 * @param ?IReflectionTreatFileOption $option 
 * @return igk\tools\Reflector\ReflectorContainer 
 */
function treat_files($input, $options = null)
{
    list($s, $reflector) = igk_extract(init_treat(), 's|reflector');
    if (is_string($options)) {
        $options = json_decode($options);
    }
    list($noCode, $regex, $recursive) = igk_extract($options ?? [
        'noCode' => false,
        'regex' => null,
        'recursive' => false
    ], 'noCode|regex|recursive');
    try {
        if (is_dir($input)) {
            IO::GetFiles($input, function ($f) use ($s, $reflector, $input, $noCode, $regex) {
                $regex = $regex ?? "/\.php$/";
                if (preg_match($regex, $f)) {
                    if (realpath($f) === __FILE__)
                        return;
                    $s->regex->resetTreatment();
                    $info = init_info();
                    $info->noCode = $noCode;
                    $info->input = $input;
                    treat_file($f, $s, $reflector, $info);
                }
                return false;
            }, $recursive);
        } else if (is_file($input)) {
            $s->regex->resetTreatment();
            $info = init_info();
            $info->noCode = $noCode;
            $info->input = $input;
            treat_file($input, $s, $reflector, $info);
        }
    } catch (\Exception $ex) {
        Logger::danger('ERROR : ' . $ex->getMessage());
    }
    return $reflector;
}
function clean_info($info)
{
    $info->lastDocBlock = null;
    $info->extends = [];
    $info->modifiers = [];
    $info->implements = [];
    $info->methods = [];
    $info->vars = [];
    $info->var_type = $info->dcmode = null;
}
/**
 * reflector info
 * @return object 
 */
function init_info()
{
    return (object)[
        'start' => false,
        'file' => null,
        'lastDocBlock' => null,
        'namespace' => null,
        'extends' => [],
        'implements' => [],
        'modifiers' => [],
        'global_uses' => [],
        'functions' => [], // function list
        'function' => null, // function tag
        'dcmode' => null,
        'start_detect' => [], // start detection flector detected 
        'vars' => [],
        'var_type' => null,
        'dcvalue' => null,
        'methods' => null,
        'class_def' => false, // init class def flag 
        'class_modifiers' => [],
        'class_docBlock' => null,
        // use loading properties 
        'use' => [],
        'use_block' => null,
        'use_mark' => [],
        'use_type' => null, // null|'function' 
        'use_as' => null, // as definition
        'refmethod' => false, // 'refmethod flag'
        'global_comments' => [],
        'method' => null,
        // options section 
        'noCode' => false,
        // global condition management flag
        'condition_start' => false,
        'condition_start_condition' => false,

        'gdc_cond' => null, // last gdc condition 
        'conditional_functions' => [],
        'def_chain' => null,
        'curl_start' => false,

        // use to filter 

        'buffered_filter' => null,
        'filter' => false, // filter flag
    ];
}
function init_def_chain(string $type, $info)
{
    return Activator::CreateNewInstance(DCChainModeDefinition::class, [
        'level' => -1, //depending on defintion level 
        'type' => $type,
        'parent' => $info->def_chain,
        'function' => null,
        'method' => null,
        'class' => null,
        'interface' => null,
        'trait' => null,
        'dcmode' => $info->dcmode
    ]);
}
function move_to_parent($info)
{
    $info->def_chain = $info->def_chain ? $info->def_chain->parent : null;
}
/**
 * check if is a multiple entities
 * @param mixed $v 
 * @return bool 
 */
function is_multiple_def_entities($v)
{
    if (is_null($v)) {
        return false;
    }
    $tc = [];
    unset($v['function']);
    unset($v['global_script']);
    $r = count(array_filter(array_merge($tc, ...array_values(array_map(function ($a) {
        return $a ? $a : null;
    }, $v)))));
    return ($r > 1);
}  
/**
 * 
 * @param mixed $info 
 * @param mixed $reflector 
 * @return Closure(RegexMatcherCapture $e, RegexDetectInfo $g, string $src, int $pos): void 
 */
function php_code_handler($info, $reflector)
{
    return function (RegexMatcherCapture $e, RegexDetectInfo $g, string $src, int $pos) use (&$reflector, $info) {
        if (!$info->start) {
            return;
        }
        igk_is_debug() && Logger::info('tokenID:' . $e->tokenID . "[" . $e->value . "]");
        initialize($info, $e, $src, $pos);
        if ($info->global_comments && $e->tokenID != 'single-line-comment') {
            $reflector->global_comments = $info->global_comments;
            $info->global_comments = false;
        }
        if ($info->dcmode == 'dc-anonymous-func') {
            if ($e->tokenID == 'curl-brank') {
                if ($info->def_chain->level === 0) {
                    move_to_parent($info);
                    restore_def_chain($info);
                }
                if ($info->def_chain) {
                    $info->def_chain->level = max(0, $info->def_chain->level - 1);
                }
            }
            return;
        }
        $v_filter = false;
        if ($filter = $info->buffered_filter) {
            if (!$filter->filter($e, $src, $info, $reflector)) {
                $reflector->global_script->output .= $filter->render();
                $reflector->global_script->offset = $e->to;
                $info->buffered_filter = null;
            }
            return;
        }
        $info->filter = &$v_filter;
        php_code_handle_core($info, $e, $src, $reflector);

        if (is_null($e->parentInfo)) {
            if (is_null($reflector->global_script)) {
                $reflector->global_script = new RegexDetectBuffer();
                $reflector->global_script->source = $src;
            }
            if (is_null($info->dcmode)) {
                if (!$v_filter) {
                    $reflector->global_script->replace($e, $e->value);
                } else {
                    $reflector->global_script->offset = $e->to;
                }
            }
        }
    };
}
/**
 * 
 * @param mixed $info 
 * @param mixed $e 
 * @param mixed $src 
 * @param mixed $reflector 
 * @return void 
 * @throws Exception 
 * @throws IGKException 
 */
function php_code_handle_core($info, $e, $src, $reflector)
{
    $f = $info->file;
    $v_filter = &$info->filter;
    switch ($e->tokenID) {
        case 'php-start':
            $v_filter = true;
            break;
        case 'dc-use-anonymous':
            if ($info->dcmode == 'dc-function-def') {
                if (!empty($info->function->name)) {
                    igk_die('can\'t \'use\' name on named function');
                }
            } else {
                if ($info->dcmode == '') {
                }
                igk_die('dc-use-anonymous not allowed');
            }
            break;
        case 'dc-typedef':
            store_litteral_info($info, $e);
            $info->var_type = $e->value;
            break;
        case 'single-line-comment':
            if (!$e->parentInfo && is_array($info->global_comments)) {
                $info->global_comments[] = $e->value;
                $v_filter = true;
            }
            break;
        case 'class-def':
            $v_filter = true;
            $type = $e->beginCaptures[1][0];
            $name = $e->beginCaptures[2][0];
            if ($info->namespace) {
                $name = implode('\\', [$info->namespace, $name]);
            }
            // store_file($reflector, $f);
            // Logger::info('detect: '.$f);
            // Logger::warn('value:'.$e->value);
            $odef = store_def($reflector, $f, $type, $name);
            $odef->docBlock = $info->class_docBlock;
            $odef->extends = $info->extends;
            $odef->implements = $info->implements;
            $odef->vars = $info->vars;
            $odef->methods = $info->methods;
            $odef->modifiers = implode(' ', $info->class_modifiers);
            $odef->uses = $info->use;
            $odef->uses_block = $info->use_block;
            $info->use_block = null;
            $found = true;
            $info->class_modifiers = [];
            $info->use = [];
            $info->class_def = false;
            clean_info($info);
            break;
        case 'global-use':
            $info->global_uses[] = $e->value;
            $v_filter = true;
            break;
        case 'comment-docbloc':
            $info->lastDocBlock = $e->value;
            $v_filter = true;
            break;
        case 'namespace':
        case 'namespace-block':
            $info->namespace = $e->beginCaptures[1][0];
            break;
        case 'dc-value':
            if ($info->dcvalue) {
                $info->dcvalue->value = $e->value;
            }
            break;
        case 'dc-method': // detect method inside a class or use 
            if (!empty($info->dcmode)) {
                if ($info->dcmode == 'use') {
                    $info->use_type = 'function';
                    break;
                } else
                    igk_die('invalid method detection: ' . $info->dcmode);
            }
            $info->dcmode = 'method';
            if ((strrpos($e->value, '&') > 0)) {
                $info->refmethod = 1;
            }
            break;
        case 'dc-as':
            if ($info->dcmode == 'use') {
                $info->use_mark[count($info->use_mark) - 1] = $info->use_mark[count($info->use_mark) - 1] . " as " . $e->captures[1][0];
            }
            break;
        case 'dc-cond':
            // condition or function argument 
            // depend on methods
            if (!$e->parentInfo || ($e->parentInfo->match->tokenID != 'dc-cond')) {
                if ($info->dcmode == 'dc-function') {

                    if ($info->function) {
                        $info->function->condition && igk_die('condition already set');
                        $info->function->condition = $e->value;
                    } else {
                        // + | start anonymous function declaration 
                        $info->dcmode = 'dc-anonymous-func';
                        $info->def_chain = init_def_chain('anonymous-function', $info);
                        $info->def_chain->level = -1;
                    }
                }
                $info->gdc_cond = $e->value;
                store_cond($info, $e);
            }
            break;
        case 'dc-return-type':
            if ($info->method)
                $info->method->returnType = $e->value;
            break;
        case 'stop-method_declaration':
            if (in_array($info->dcmode, ['method', 'use'])) {
                $info->method = null;
                $info->dcmode = 0;
            }
            break;
        case 'curl-childs-brank':
            // because of chain -- check that parent is not a curl-childs-branch
            if ($e->parentInfo->match->tokenID != 'curl-childs-brank') {
                store_child_code($info, $e);
            }
            break;
        case 'curl-brank':
            if ($info->def_chain) {
                if ($info->def_chain->level <= 0) {
                    if ($info->def_chain->type == 'method') {
                        store_child_code($info, $e);
                    } elseif ($info->function) {
                        $info->dcmode = 'dc-function-def';
                        $info->function->conditional = $info->condition_start;
                        $info->function->conditional_condition = $info->condition_start_condition;
                        store_child_code($info, $e);
                        $info->function = null;
                    }
                    move_to_parent($info);
                    restore_def_chain($info);
                    $v_filter = true;
                }
                if ($info->def_chain) {
                    $info->def_chain->level = max(0, $info->def_chain->level - 1);
                }
            } else {
                // clear global data info 
                if (!$info->dcmode) {
                    $info->var_type = null;
                }
                $info->curl_start = false;
            }
            break;
        case 'dc-function':
            // global conditional function
            // start  
            if ($info->dcmode == 'dc-function-def') {
                // start nested function definition
                // backup nested  
                $info->def_chain->function = $info->function;
                $info->dcmode = 'dc-function';
                $info->function = null;
            } else {

                $info->dcmode &&
                    igk_die('invalid declaration. except in conditional gdc function ');
                $info->dcmode = 'dc-function'; // start global function definition 
            }
            if ((strrpos($e->value, '&') > 0)) {
                $info->refmethod = 1;
            }
            break;
        case 'gdc-litteral':
            // litteral. for dc function mode 
            if ($info->dcmode == 'dc-function') {
                $method = new DCMethodInfo;
                $method->name = $e->value;
                $method->isRef = $info->refmethod;
                $method->phpDocComment = $info->lastDocBlock;
                $method->isNested = true && $info->function;
                if ($info->class_def) {
                    // igk_wln_e(__FILE__ . ":" . __LINE__, "init class definition .... method .... ");
                    $method->modifiers = implode(' ', array_unique($info->modifiers));
                    $info->methods[$e->value] = $method;
                    $info->method = $method;
                    $info->dcmode = 'method';
                    $info->def_chain = init_def_chain('method', $info);
                } else {

                    $info->functions[$e->value] = $method;
                    $info->function = $method;
                    store_def($reflector, $f, 'function', $method);
                    // init definition chain 
                    $info->dcmode = 'dc-function-def';
                    $info->def_chain = init_def_chain('function', $info);
                }
                $info->modifiers = [];
                $info->lastDocBlock = null;
                $info->refmethod = false;
            } else {
                store_litteral_info($info, $e);
            }
            break;
        case 'end-instruction':
            if ($info->def_chain) {
                if ($info->def_chain->level == -1) {
                    $info->def_chain->clean($info);
                    move_to_parent($info);
                    restore_def_chain($info);
                }
            }
            reset_global_info($info);
            break;
        case 'dc-variable':
        case 'dc-extends':
        case 'dc-implements':
        case 'dc-modifier':
        case 'dc-use':
            $k = igk_str_rm_start($e->tokenID, 'dc-');
            if ($k == 'variable') {
                if (is_null($info->dcmode)) {
                    $info->vars[$e->value] = Activator::CreateNewInstance(DCPropertyInfo::class,  (object)[
                        'value' => null,
                        'modifiers' => implode(' ', $info->modifiers),
                        'type' => $info->var_type,
                        'comment' => $info->lastDocBlock,
                    ]);
                    $info->dcvalue = $info->vars[$e->value];
                } else {
                    $info->dcvalue = null;
                }
                $info->modifiers = [];
                $info->var_type = null;
                $info->lastDocBlock = null;
            } else if ($k == 'modifier') {
                $info->modifiers[] = $e->value;
            } else {
                $info->dcmode = $k;
            }
            break;
        case 'dc-typelitteral':
            store_litteral_info($info, $e);
            break;
        case 'gdc-operator':
            reset_global_info($info);
            break;
        case 'gdc-condition-start':
            $v_filter = true;
            $info->buffered_filter = ReflectorCodeFilterFactory::CreateFilter($e->value) ?? igk_die('missing filter creation');
            $info->buffered_filter->start = $e->from;
            break;
    }
}
/**
 * 
 * @param mixed $info 
 * @param mixed $e 
 * @return void 
 * @throws Exception 
 */
function store_litteral_info($info, $e)
{
    if ($info->dcmode) {
        $info->{$info->dcmode}[] = $e->value;
        if ($info->dcmode == 'use') {
            $v = $e->value;
            if ($info->use_type) {
                $v = $info->use_type . ' ' . $v;
            }
            $info->use_mark[] = $v;
            $info->use_type = null;
            unset($v);
        }
    } else {
        if (in_array('const', $info->modifiers)) {
            $info->vars[$e->value] = (object)[
                'value' => $info->dcvalue,
                'modifiers' => implode(' ', $info->modifiers)
            ];
            $info->modifiers = [];
            $info->var_type = null;
            $info->dcvalue = $info->vars[$e->value];
        } else {
            if (empty($info->var_type)) {
                $info->var_type = $e->value;
            } else {
                if ($info->curl_start) {
                } else {
                    igk_die('not a valid litteral declaration.');
                }
            }
        }
    }
}
function reset_global_info($info)
{
    if (!$info->dcmode) {
        $info->var_type = null;
    }
}
function restore_def_chain($info)
{
    if ($chain = $info->def_chain) {
        switch ($t = $chain->type) {
            case 'function':
                $info->function = $chain->function;
                break;
            default:
                $info->{$t} = $chain->{$t};
                break;
        }
        $info->dcmode = $chain->dcmode;
    } else {
        $info->dcmode = null;
    }
}
/**
 * 
 */
function treat_file(string $f, RegexDetectHandler $s, ReflectorContainer $reflector, $info)
{
    $src = file_get_contents($f);
    igk_is_debug() && Logger::info('remove csharp summary');
    $src = igk_str_rm_php_csharp_summary($src);
    $found = false;
    $s->detect(
        $src,
        php_code_handler($info, $reflector, $found, $f),
        function ($g, $src, $pos) use ($info) {
            init_chainlist($info, $g, $src, $pos);
        }
    );

    if ($filter = $info->buffered_filter) {
        $s = $filter->render();
        $reflector->global_script->output .= $s;
        $info->buffered_filter = null;
    }

    if ($info->global_uses) {
        $p = array_map(function ($a) {
            return format($a);
        }, $info->global_uses);
        $reflector->global_uses = $p;
    }
    if ($info->functions) {
        $reflector->functions = $info->functions;
    }
    if ($info->namespace) {
        $reflector->namespace = $info->namespace;
    }
    if ($info->global_comments) {
        $reflector->global_comments = $info->global_comments;
        $info->global_comments = false;
    }
    if (!$reflector->global_script->isEmpty()) {
        if (!isset($reflector->files[$f])) {
            $reflector->files[$f] = [];
        }
        $tf = &$reflector->files[$f];
        $tf['global_script'] =
            trim($reflector->global_script->output);
    }
}
function get_formatter()
{
    return ((($f = igk_app()->getService('php-formatter')) instanceof ITextCodeFormatter) ? $f : null)
        ?? new FormatPHPCode;
}
/**
 * render function 
 * @param mixed $c 
 * @param mixed $s 
 * @return void 
 */
function render_function($c, $s)
{
    $formatter = get_formatter();
    $render_i = function ($i) use ($formatter) {
        $ref = $i->isRef ? ' & ' : ' ';
        $p = '';
        if (!($p = $i->phpDocComment)) {
            $p = implode("\n", [
                "/**",
                "* return mixed",
                "*/",
            ]) . "\n";
        } else {
            $p .= "\n";
        }
        $c = $i->code ? $formatter->format($i->code) : ';';

        $src = $p . sprintf('function%s%s%s', $ref . $i->name, $i->condition, $c);



        return $src;
    };
    // if ($s->renderMultiple) {
    // } else {
    // }
    echo implode("\n", array_map(function ($i) use ($s, $render_i) {
        if (is_array($i) || $i->conditional) {
            // multiple function declaration or conditional function 
            $r = !is_array($i) ? [$i] : $i;
            $c = ['if' => [], 'elseif' => [], 'else' => [], 0 => []];
            $formatter = get_formatter();
            $cond = [];
            $elsef = false;
            $rd = [];
            while (count($r) > 0) {
                $q = array_shift($r);
                if ($q->conditional == 'else') {
                    if ($elsef) {
                        igk_die('can only have one else segment');
                    }
                    $elsef = $q;
                } else {
                    $cond[] = $q->conditional_condition;
                    $rd[] = $formatter->format('if ' . $q->conditional_condition . "{\n" . $render_i($q) . "\n}");
                }
            }
            if ($elsef) {
                if( empty($cond)){
                    $cond = [$elsef->conditional_condition];
                }
                $s = $formatter->format('if (!' . implode(' && !', $cond) . "){\n" . $render_i($elsef) . "\n}");
                $rd[] = $s;
            }
            return implode("\n", $rd);
        }
        return $render_i($i);
    }, $c)) . "\n";
}


if (!function_exists('harmonize_render')) {
    function harmonize_render($s)
    {
        $content = '';
        if (!empty($s->files)) {

            foreach ($s->files as $k => $v) {
                if (isset($v['mixed'])) {
                    continue;
                }
                $content = '';
                if (is_multiple_def_entities($v)) {
                    Logger::info('is mutiple: ' . $k);
                    $s->singleDefinitionPerFile = false;
                    ob_start();
                    echo "<?php\n";
                    if ($s->global_comments) {
                        echo implode("\n", $s->global_comments) . "\n";
                    }
                    $bck = $s->global_comments;
                    $s->global_comments = [];
                    $s->renderMultiple = false;
                    // only on element can 'to namepsace definition 
                    foreach (['function', 'interface', 'trait', 'class'] as $gk) {
                        if ($c = igk_getv($v, $gk)) {
                            ksort($c);
                            if ($gk == 'function') {
                                render_function($c, $s);
                            } else {
                                echo implode("\n", array_map(function ($i) use ($s) {
                                    $k = $i->render();
                                    $s->renderMultiple = true;
                                    return $k;
                                }, $c)) . "\n";
                            }
                        }
                    }
                    $s->renderMultiple = false;
                    $s->global_comments = $bck;
                    $content = ob_get_contents();
                    ob_end_clean();
                } else {
                    $s->singleDefinitionPerFile = true;
                    ob_start();
                    if ($s->global_comments) {
                        echo implode("\n", $s->global_comments) . "\n";
                        $s->renderMultiple = true;
                    }
                    if ($s->namespace) {
                        echo 'namespace ' . $s->namespace . ";\n";
                        $s->renderMultiple = true;
                    }
                    $found = false;
                    if ($c = igk_getv($v, 'function')) {
                        ksort($c);
                        $c = array_filter($c, function ($i) {
                            return !$i->isNested;
                        });
                        render_function($c, $s);
                        $found = true;
                    }
                    $s->singleDefinitionPerFile = !$found;
                    $bck = $s->global_comments;
                    $s->global_comments = [];
                    foreach (['interface', 'trait', 'class'] as $gk) {
                        if ($c = igk_getv($v, $gk)) {
                            echo $c[key($c)]->render();
                            $found = true;
                        }
                    }
                    $s->global_comments = $bck;

                    if ($s->global_script) {
                        echo "\n" . get_formatter()->format($s->global_script->output());
                    }

                    $content = ob_get_contents();
                    if (!$s->singleDefinitionPerFile || !$found) {
                        $content = '<?php' . "\n" . $content;
                    }
                    ob_end_clean();
                }
            }
        } else {
            ob_start();
            if ($s->global_comments) {
                echo implode("\n", $s->global_comments) . "\n";
                $s->renderMultiple = true;
            } else {
                echo  '// no definition found.' . "\n";
            }
            if ($s->global_script) {
                echo $s->global_script->output() . "\n";
            }
            $content = ob_get_contents();
            ob_end_clean();

            $content = implode("\n", [
                '<?php',
                $content
            ]);
        }
        return $content;
    }
}

if (!function_exists('harmonize_content')) {
    /**
     * harmonize content 
     * @param string $src 
     * @return string 
     */
    function harmonize_content(string $src): string
    {
        list($s, $reflector) = igk_extract(init_treat(), 's|reflector');
        $f = 'armonize-content';
        $info = init_info();
        $info->file = $f;
        $s->detect(
            $src,
            php_code_handler($info, $reflector),
            function ($g, $src, $pos) use ($info) {
                init_chainlist($info, $g, $src, $pos);
            }
        );
        $s = harmonize_render($reflector);
        return $s;
    }
}
