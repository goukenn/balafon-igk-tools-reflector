<?php
// @author: C.A.D. BONDJE DOUE
// @file: %modules%/igk/tools/Reflector/.global.php
// @date: 20250703 15:11:27

// + module entry file 

namespace igk\tools\Reflector;

use IGK\Helper\Activator;
use IGK\Helper\IO;
use IGK\System\Console\Logger;
use IGK\System\Text\RegexDetectBuffer;
use IGK\System\Text\RegexDetectHandler;
use IGK\System\Text\RegexMatcherContainer;
use IGK\System\Text\RegexMatcherUtility;

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
 * 
 */
function store_def($reflector, $file, $type, $name)
{
    store_file($reflector, $file);

    if (! isset($reflector->files[$file][$type])) {
        $reflector->files[$file][$type] = [];
    }
    $odef = new ReflectorDefinition($reflector);
    $odef->type = $type;
    $odef->name = $name;
    $odef->file = $file;
    $reflector->files[$file][$type][$name] = $odef;
    if ($ktype = igk_getv([
        'class' => 'classes',
        'interface' => 'interfaces',
        'trait' => 'traits',
        'function' => 'functions'
    ], $type)) {
        $reflector->{$ktype}[$name] = $odef;
    }
    return $odef;
}

function store_cond($info, $e)
{
    switch ($info->dcmode) {
        case 'method':
            $info->method->condition = $e->value;
            break;
    }
}
function store_child_code($info, $e, )
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
function init_chainlist($info, $g, $src, $pos)
{
    igk_is_debug() && Logger::warn('chain start: ' . $g->match->tokenID);

    if (!isset($info->start_detect[$g->pos])) {
        $info->start_detect[$g->pos] = $g;
    } else {
        if ($info->start_detect[$g->pos] !== $g) {
            igk_die('not matching');
        }
        return;
    }
    igk_is_debug() && Logger::danger('start: ' . $g->match->tokenID);
    if ($info->start) {
        if ($g->match->tokenID == 'namespace-block') {
            $info->namespace = $g->captures[1][0];
        }
    }
    if ($g->match->tokenID == 'php-start') {
        $info->start = true;
    }
    if ($g->match->tokenID == 'php-end') {
        $info->start = false;
    }
    if ($g->match->tokenID == 'curl-brank') {

        $info->dcmode = null;
    }
    if ($g->match->tokenID == 'class-def') {
        $info->class_modifiers = $info->modifiers;
        $info->class_docBlock = $info->lastDocBlock;
        $info->lastDocBlock = null;
        $info->modifiers = [];
    }
}


function init_treat()
{

    $regex = new RegexMatcherContainer;
    $block_pattern = [];
    $php_doc = $block_pattern[] = $regex->appendCommentDocBlock()->last();
    $block_pattern[] = $regex->appendSingleLineComment()->last();
    $multiline_comment = $block_pattern[] = $regex->begin('\/\*', '\*\/', 'multiline-comment')->last();
    $block_pattern[] = $regex->begin('\{', '\}', 'curl-brank')->last();
    $block_pattern[] = $regex->appendStringDetection()->last()->patterns = [
        $regex->createPattern(['match' => '\\\\.'])
    ];
    $single_line_comment = $block_pattern[] = $regex->match("\/\/.+$", "single-line-comment")->last();

    $here_doc = [];
    $regex->match('<\?php(\\s+|\\s*$)', 'php-start');
    $regex->match('\?>', 'php-end');


    $regex->autoStore = false;

    RegexMatcherUtility::appendPhpHereDoc($regex, $here_doc);

    $block_pattern = array_merge($block_pattern, $here_doc);


    $func_return_type  = $regex->begin(":", "(?=;|\{)", "dc-return-type")->last();

    $string = $regex->appendStringDetection('string', true)->last();
    $comment = $regex->appendSingleLineComment()->last();

    $implements = $regex->createPattern(['match' => '\\b(implements)\\b', 'tokenID' => 'dc-implements']);
    $extends = $regex->createPattern(['match' => '\\b(extends)\\b', 'tokenID' => 'dc-extends']);
    $use = $regex->createPattern(['match' => '\\buse\\b', 'tokenID' => 'dc-use']);
    $modifier = $regex->createPattern(['match' => '\\b(private|public|protected|abstract|final|var|const|readonly)\\b', 'tokenID' => 'dc-modifier']);
    $function = $regex->createPattern(['match' => '\\b(function)\\b(?:\\s*&\\s*)?', 'tokenID' => 'dc-method']);
    $litteral = $regex->createPattern(['match' => '\\b([a-zA-Z_][a-zA-Z_]*)\\b', 'tokenID' => 'dc-litteral']);


    $type_litteral = $regex->createPattern(['match' => '(\\\\)?([a-zA-Z_][a-zA-Z_]*)(\\\\(?:[a-zA-Z_][a-zA-Z_]*))+\\b', 'tokenID' => 'dc-typelitteral']);


    $var = $regex->createPattern(['match' => '(\\$[a-zA-Z_][a-zA-Z_]*)\\b', 'tokenID' => 'dc-variable']);
    $array = $regex->createPattern(['begin' => '\[', 'end' => '\]', 'tokenID' => 'dc-array']);
    $cond = $regex->createPattern(['begin' => '\(', 'end' => '\)', 'tokenID' => 'dc-cond']);
    $cond->patterns = [
        $comment,
        $string,
        $array,
        $cond
    ];
    $array->patterns = [
        $comment,
        $string,
        $array
    ];
    $func_return_type->patterns = [
        $comment,
        $multiline_comment,
        $string
    ];
    $concat = $regex->createPattern(['match' => '\\.', 'tokenID' => 'dc-concat']);
    $value = $regex->createPattern(['begin' => '=', 'end' => ';', 'tokenID' => 'dc-value']);
    $value->patterns = [
        $comment,
        $string,
        $array
    ];
    array_push($value->patterns, ...$here_doc);


    $body_brank = $regex->begin('\{', '\}', 'curl-brank')->last();
    $body_child_brank = $regex->begin('\{', '\}', 'curl-childs-brank')->last();
    $body_child_brank->patterns = [
        $string,
        $comment,
        $php_doc,
        $body_child_brank
    ];
    array_push($body_child_brank->patterns, ...$here_doc);

    $body_brank->patterns = [
        ["match" => ';', "tokenID" => "stop-method_declaration"],
        $use,
        $modifier,
        $var,
        $value,
        $function,
        $func_return_type,
        $cond,
        $type_litteral,
        $litteral,
        $php_doc,
        $body_child_brank,
    ];

    $regex->autoStore = true;


    $regex->append($modifier);

    $block_pattern[] = $g = $regex->begin('\\b(interface|trait|class)\\b\\s*([a-zA-Z_][a-zA-Z_0-9]*)\\b', '(?<=\})', 'class-def')->last();

    $g->patterns = [
        $implements,
        $extends,
        $type_litteral,
        $litteral,
        $body_brank
    ];

    $global_use = $regex->begin('\\buse\\b', ';', 'global-use')->last();

    $ns = $regex->match('namespace\\b\\s*([a-zA-Z_][a-zA-Z_]*(?:\\\\[a-zA-Z_][a-zA-Z_]*)*)\\s*;', 'namespace');

    // namespace block
    $block_pattern[] = $global_use;
    $block_pattern[] = $modifier;

    $c = $regex->createPattern([
        'begin' => '\{',
        'end' => '\}',
        'tokenID' => 'namespace-curl-block',
        'patterns' => $block_pattern
    ]);
    $ns_b = $regex->begin('namespace\\b\\s*([a-zA-Z_][a-zA-Z_]*(?:\\\\[a-zA-Z_][a-zA-Z_]*)*)\\s*(?=\{)', '(?<=\})', 'namespace-block')->last();
    $ns_b->patterns = [$c];


    $s = new RegexDetectHandler($regex);
    $regex->resetTreatment();
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
 * 
 * @param mixed $input 
 * @return igk\tools\Reflector\ReflectorContainer 
 */
function treat_files($input, $options=null)
{
    list($s, $reflector) = igk_extract(init_treat(), 's|reflector');
    if (is_string($options)){
        $options = json_decode($options);
    }
    list($noCode, $regex, $recursive) = igk_extract($options ?? [
            'noCode'=>false,
            'regex'=>null,
            'recursive'=>false
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
        } else if (is_file($input)){
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
function clean_info($info){
    $info->lastDocBlock = null;
    $info->extends = [];
    $info->modifiers = [];
    $info->implements = [];
    $info->methods = [];
    $info->vars = [];
    $info->var_type = $info->dcmode = null;
}
function init_info()
{
    return (object)[
        'start' => false,
        'lastDocBlock' => null,
        'namespace' => null,
        'extends' => [],
        'implements' => [],
        'modifiers' => [],
        'global_uses' => [],
        'dcmode' => null,
        'start_detect' => [],
        'vars' => [],
        'var_type' => null,
        'dcvalue' => null,
        'methods' => null,
        'class_modifiers' => [],
        'class_docBlock' => null,
        'use' => [],
        'use_block' => null,
        'use_mark' => [],
        'refmethod' => false, // 'refmethod flag'
        'global_comments' => [],
        // options section 
        'noCode'=>false,
    ];
}

function is_multiple_def_entities($v){
    $tc = [];
    $r = count(array_filter(array_merge($tc, ...array_values(array_map(function ($a) {
        return $a ? $a : null;
    }, $v)))));
    return ($r > 1);
}

function treat_file(string $f, $s, $reflector, $info)
{
    $src = file_get_contents($f);
    $src = igk_str_rm_php_csharp_summary($src);
    $found = false;

    $s->detect($src, function ($e, $g, $src, $pos) use ($f, &$found, &$reflector, $info) {
        if (!$info->start) {
            return;
        }
        igk_is_debug() && Logger::info('tokenID:' . $e->tokenID . "[" . $e->value . "]");
        initialize($info, $e, $src, $pos);
        if ($info->global_comments && $e->tokenID != 'single-comment') {

            $reflector->global_comments = $info->global_comments;
            $info->global_comments = false;
        }
        switch ($e->tokenID) {
            case 'single-comment':
                if (!$e->parentInfo && is_array($info->global_comments)) {
                    $info->global_comments[] = $e->value;
                }
                break;
            case 'class-def':
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
                clean_info($info);

                break;
            case 'global-use':
                $info->global_uses[] = $e->value;
                break;
            case 'comment-docbloc':
                $info->lastDocBlock = $e->value;
                break;
            case 'namespace':
            case 'namespace-block':
                $info->namespace = $e->beginCaptures[1][0];
                break;
            case 'dc-value':
                $info->dcvalue->value = $e->value;
                break;
            case 'dc-method':
                if (!empty($info->dcmode)) {
                    igk_die('invalid method detections');
                }
                $info->dcmode = 'method';
                if ((strrpos($e->value, '&') > 0)) {
                    $info->refmethod = 1;
                }
                break;
            case 'dc-cond':
                // depend on methods
                store_cond($info, $e);
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
            case 'dc-variable':
            case 'dc-extends':
            case 'dc-implements':
            case 'dc-modifier':
            case 'dc-use':
                $k = igk_str_rm_start($e->tokenID, 'dc-');
                if ($k == 'variable') {
                    $info->vars[$e->value] = Activator::CreateNewInstance(DCPropertyInfo::class,  (object)[
                        'value' => null,
                        'modifiers' => implode(' ', $info->modifiers),
                        'type' => $info->var_type,
                        'comment' => $info->lastDocBlock,
                    ]);

                    $info->modifiers = [];
                    $info->var_type = null;
                    $info->lastDocBlock = null;
                    $info->dcvalue = $info->vars[$e->value];
                } else if ($k == 'modifier') {
                    $info->modifiers[] = $e->value;
                } else {
                    $info->dcmode = $k;
                }
                break;
            case 'dc-litteral':
            case 'dc-typelitteral':
                if ($info->dcmode == 'method') {
                    $method = new DCMethodInfo;
                    $method->name = $e->value;
                    $method->isRef = $info->refmethod;
                    $method->phpDocComment = $info->lastDocBlock;
                    $method->modifiers = implode(" ", $info->modifiers);
                    $info->methods[$e->value] = $method;
                    $info->method =  $method;
                    $info->modifiers = [];
                    $info->lastDocBlock = null;
                    $info->refmethod = false;
                } else {
                    if ($info->dcmode) {
                        $info->{$info->dcmode}[] = $e->value;
                        if ($info->dcmode == 'use') {
                            $info->use_mark[] = $e->value;
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
                                igk_die('not a valid litteral declaration.');
                            }
                        }
                    }
                }
                break;
        }
    }, function ($g, $src, $pos) use ($info) {

        init_chainlist($info, $g, $src, $pos);
    });
    if ($info->global_uses) {
        $p = array_map(function ($a) {
            return format($a);
        }, $info->global_uses);
        $reflector->global_uses = $p;
    }
}
