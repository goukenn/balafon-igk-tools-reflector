<?php

// @author: C.A.D. BONDJE DOUE
// @filename: ReflectorContainer.php
// @date: 20250703 14:44:13
// @desc: 

namespace igk\tools\Reflector;

use IGK\System\IO\StringBuilder;

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
            $sb->append(' extends ' . implode(', ', $this->extends));
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
                $sb->appendLine(format_comment($comment, $d));
                $sb->appendLine(sprintf('%s%s%s%s', $d, $v->modifiers . ' ', $k, $v->value ?? ';'));
            }
        }
        $tm = &$this->methods;
        if ($tm) {
            ksort($tm);
            foreach ($tm as $k => $v) {
                $comment = ltrim($v->phpDocComment ?? implode("\n" . $d, [
                    "/**",
                    "* @return mixed",
                    "*/",
                ]));
                $sb->appendLine(format_comment($comment, $d));
                $rt = '';
                if ($v->returnType) {
                    $rt = format($v->returnType);
                }
                $ref = $v->isRef ? ' & ' : ' ';
                $sb->appendLine(sprintf('%s%s%s%s', $d, $v->modifiers . ' function' . $ref, $k . $v->condition . $rt, $v->code ?? ';'));
            }
        }
        $sb->appendLine('}');
        $s = $sb . '';
        if ($this->m_host->singleDefinitionPerFile)
            $s = "<?php\n" . $s;
        return $s;
    }
}
