<?php
// @author: C.A.D. BONDJE DOUE
// @filename: ReflectorContainer.php
// @date: 20250703 14:44:13
// @desc: 
namespace igk\tools\Reflector;
use IGK\System\IO\StringBuilder;
use IGK\System\Text\ITextCodeFormatter;

/**
 * definition code 
 * @package 
 */
class ReflectorDefinition
{
    /**
     * type will be the render 
     * @var mixed
     */
    var $type;
    var $name;
    var $file;
    var $docBlock;
    var $implements;
    var $extends;
    var $modifiers;
    var $vars;
    var $uses;
    var $uses_block;
    var $functions;
    var $methods;
    private $m_host;
    public function __construct($host)
    {
        $this->m_host = $host;
    }
    /**
     * render definition 
     */
    public function render()
    {
        $sb = new StringBuilder;
        if ($v_gcm = $this->m_host->global_comments) {
            $sb->appendLine(implode("\n", $v_gcm) . "\n");
        }
        $depth = 1;
        $d = str_repeat(" ", $depth * 4);
        $m = !empty($this->modifiers) ? $this->modifiers . ' ' : '';
        $ns = '';
        if (($h = dirname(igk_uri($this->name))) && ($h != '.')) {
            $ns = igk_ns_name($h);
            if (!$this->m_host->renderMultiple) {
                $sb->appendLine('namespace ' . igk_ns_name($h) . ';' . "\n");
            }
        }
        if ($gu = $this->m_host->global_uses) {
            sort($gu);
            $sb->appendLine(implode("\n", $gu) . "\n");
        }
        $n = basename(igk_uri($this->name));
        $comment = $this->docBlock ?? implode("\n", array_filter([
            '/**',
            '*',
            $ns ? "* @package " . $ns : null,
            "*/"
        ]));
        $sb->appendLine(format_comment($comment, ''));
        $sb->append(sprintf('%s%s%s', $m, $this->type . ' ', $n));
        if ($this->extends) {
            $sb->append(' extends ' . implode('', $this->extends));
        }
        if ($this->implements) {
            sort($this->implements);
            $sb->append(' implements ' . implode(', ', $this->implements));
        }
        $sb->appendLine('{');
        if ($this->uses) {
            $u = $this->uses;
            sort($u);
            $end = ';';
            $lts = [];
            if ($this->uses_block) {
                //$end = implode("", $this->uses_block);
                $r = [];
                ksort($this->uses_block);
                foreach ($this->uses_block as $k => $v) {
                    array_push($r, ...explode(',', $k));
                    $lts[] = ($d . "use " . $k . $v);
                }
                $r = array_unique($r);
                $u = array_diff($u, $r); //, $u);
            }
            if ($u)
                $lts[] = ($d . "use " . implode(", ", $u) . $end);
            sort($lts);
            $sb->appendLine(implode("\n", $lts));
        }
        $ts = &$this->vars;
        if ($ts) {
            ksort($ts);
            /**
             * @var DCPropertyInfo $v
             */
            foreach ($ts as $k => $v) {
                $comment = $v->comment ?? $d . implode("\n" . $d, [
                    "/**",
                    "* @var mixed",
                    "*/",
                ]);
                $ssb = '';
                if ($v->modifiers ){
                    $ssb.= $v->modifiers. ' ';
                }
                if ($v->type){
                    $ssb.= $v->type.' ';
                }
                $sb->appendLine(format_comment($comment, $d));
                $sb->appendLine(sprintf('%s%s%s%s', $d, $ssb, $k, $v->value ?? ';'));
            }
        }
        $tm = &$this->methods;
        $formatter = ((($f = igk_app()->getService('php-formatter')) instanceof ITextCodeFormatter) ? $f : null)
            ?? new FormatPHPCode;
        if ($tm) {
            ksort($tm);
            foreach ($tm as $k => $v) {
                $v_def_comment = igk_getv([
                    '__construct'=>'* .ctr',
                    '__destruct'=>'* .destructor'
                ],$v->name );
                $comment = ltrim($v->phpDocComment ?? implode("\n" . $d, array_filter([
                    "/**",
                    $v_def_comment ,
                    "* @return mixed",
                    "*/",
                ])));
                $sb->appendLine(format_comment($comment, $d));
                $rt = '';
                if ($v->returnType) {
                    $rt = format($v->returnType);
                }
                $ref = $v->isRef ? ' & ' : ' ';
                $rm = $v->modifiers ? $v->modifiers.' ':'';
                $code = ';';
                if ($v->code){
                    $formatter->baseDepth=1;
                    $code = ltrim($formatter->format($v->code)); 
                }


                $sb->appendLine(sprintf('%s%s%s%s', $d, $rm.'function' . $ref, $k . $v->condition . $rt, $code));
            }
        }
        $sb->appendLine('}');
        $s = $sb . '';
        if ($this->m_host->singleDefinitionPerFile)
            $s = "<?php\n" . $s;
        return $s;
    }
}