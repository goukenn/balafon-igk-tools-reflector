<?php
// @author: C.A.D. BONDJE DOUE
// @file: ReflectorCodeConditionalFilter.php
// @date: 20250721 16:33:57
namespace igk\tools\Reflector;

use IGK\System\Console\Logger;
use IGK\System\Text\RegexDetectInfo;
use IGK\System\Text\RegexMatcherCapture;
use igk\tools\Reflector\ReflectorGlobalConditionBuffer;

/**
 * 
 * @package igk\tools\Reflector
 * @author C.A.D. BONDJE DOUE
 */
class ReflectorCodeConditionalFilter extends ReflectorCodeFilterFactory
{
    var $body_output;
    var $cond;
    private $m_body_block = false;
    private $m_brankdepth = -1;
    const READ_BODY = 2;
    const END = 3;
    const SKIP_READ_FUNC = 4;

    /**
     * store dcmode on conditional
     * @var int|bool
     */
    private $m_dcmode = false;

    public function __construct()
    {
        $this->body_output = new ReflectorGlobalConditionBuffer;
    }
    /**
     * render body block
     * @return string 
     */
    public function render(): string
    {
        $s = $this->body_output . '';
        if ($this->m_body_block) {
            if (empty(trim($s))) {
                $s = '';
            }
            if (empty(rtrim($s))) {
                $s = '{}';
            } else
                $s = sprintf(implode("\n", array_filter(['{',  rtrim($s), '}'])));
        } else {
            if ('else'==$this->type){
                return '';
            }
            $s = ';';
        }
         if ('else'==$this->type){
            $s = sprintf('%s %s', $this->type, $s);
         }else
            $s = sprintf('%s %s%s', $this->type, $this->cond, $s);
        return $s;
    }
    /**
     * 
     * @param RegexDetectInfo $e 
     * @param string $source 
     * @param int $pos 
     * @return void 
     */
    public function start(RegexDetectInfo $e, string $source, int $pos)
    {
        $token_id = $e->match->tokenID;
        switch ($token_id) {
            case 'curl-brank':
                if (!$this->m_body_block)
                    $this->m_body_block = true;
                if (($this->m_dcmode == 1) || ((($this->type == 'else') && ($this->m_dcmode === false)))) {
                    $this->m_dcmode = self::READ_BODY;
                    $this->body_output->from = $e->pos;
                    $this->body_output->start = $e->pos + 1;
                }
                $this->m_brankdepth++;
                break;
        }
    }
    /**
     * close buffer
     * @param mixed $e 
     * @param mixed $src 
     * @param mixed $info 
     * @param mixed $reflector 
     * @param null|int $to 
     * @return void 
     */
    function _closeBuffer($e, $src, $info, $reflector, ?int $to = null, ?string $c = null)
    {
        $q = $this;
        $r = $q->body_output . '';
        $to = $to ?? $e->from;
        if (is_null($c)) {
            if (!$q->m_body_block || !empty(trim($r))) {
                $c = $q->render();
            }
        }
        if ($tq = $q->parent) {
            $c && $tq->body_output->append($c, $to);
            $info->buffered_filter = $tq;
        } else {
            $c && $reflector->global_script->output .= $c;
            $reflector->global_script->offset = $to;
            $info->buffered_filter = null;
        }
        $info->condition_start = null;
        $info->condition_start_condition = null;
    }
    /**
     * 
     * @param mixed $e 
     * @param mixed $src 
     * @return bool 
     */
    public function filter(RegexMatcherCapture $e, string $src, $info, ReflectorContainer $reflector): bool
    {
        $token_id = $e->tokenID;
        $q = $this;
        if ($q->m_dcmode == self::SKIP_READ_FUNC) {
            php_code_handle_core($info, $e, $src, $reflector);
        }


        switch ($token_id) {
            case 'gdc-condition-start':
                $start_new = false;
                if (($q->m_dcmode == self::READ_BODY) && ($e->value == 'else')) {
                    igk_die('outside `else` not allowed!');
                }
                if ($q->m_dcmode == self::END) {
                    $start_new = true;
                    if (($e->value == 'elseif') || ($e->value == 'else')) {
                        if (!$q->m_body_block) {
                            $q->m_body_block = true;
                        }
                        $c = $q->render();
                        $q->_closeBuffer($e, $src, $info, $reflector, null, $c);
                    }
                }


                $v_if = static::CreateFilter($e->value);
                $v_if->parent = $start_new ? $this->parent : $this;
                $v_if->start = $e->from;
                if ($v_if->type == 'else'){
                    $v_if->cond = $q->cond;
                }
                $info->buffered_filter = $v_if;
                $info->filter = true;
                $info->condition_start = null;
                $info->condition_start_condition = null;
                break;
            case 'dc-cond':
                if ($q->m_brankdepth == -1) {
                    if (empty($q->cond) && (!$e->parentInfo || ($e->parentInfo->match->tokenID != $token_id))) {
                        if ($q->type == 'else') {
                            igk_die('`else` does not support condition');
                        }
                        $q->cond = $e->value;
                        $q->m_dcmode = 1;
                    }
                }
                break;
            case 'end-instruction':
                if (($q->m_dcmode == 1) && ($q->cond)) {
                    // + | stop reading 
                    if ($q->parent) {
                        $q->_closeBuffer($e, $src, $info, $reflector, $e->to);
                        return true;
                    }
                    return false;
                }
                if (($q->m_dcmode == self::SKIP_READ_FUNC) && ($q->m_brankdepth == -1)) {
                    $q->_endread_function($e);
                }
                break;
            case 'curl-brank':
                if (($q->m_dcmode == 1) && ($q->m_brankdepth == -1)) {
                    // curl for else match 
                    // only empty branket
                    $q->body_output->start = $e->from + 1;
                    $r = $q->body_output->done($src, $e);
                    $q->m_dcmode = self::END;
                    if ($tq = $q->parent) {
                        if (!empty(trim($r))) {
                            $c = $q->render();
                            $tq->body_output->append($c, $e->to);
                        }
                        $tq->body_output->start = $e->to;
                        if ($q->type != 'if')
                            $info->buffered_filter = $tq;
                        // passgin to parent 
                    }
                    break;
                }
                $else_stop = ($q->m_dcmode === false) && ($q->type == 'else');
                if ($else_stop || ($q->m_dcmode == 2)) {
                    $q->m_brankdepth = max(0, $q->m_brankdepth - 1);

                    if (!$e->parentInfo || ($q->m_brankdepth == 0)) {
                        if ($q->body_output->skip) {
                            $q->body_output->start = $e->to;
                        }
                        $r = $q->body_output->done($src, $e);
                        $q->m_dcmode = self::END;
                        if ($tq = $q->parent) {
                            if (!$q->m_body_block || !empty(trim($r))) {
                                $c = $q->render();
                                $tq->body_output->append($c, $e->to);
                            }
                            $tq->body_output->start = $e->to;
                            if ($q->type != 'if')
                                $info->buffered_filter = $tq;
                        }
                    }
                }
                if ($q->m_dcmode == self::SKIP_READ_FUNC) {
                    if (is_null($info->function)) {
                        // end read function 
                        $q->_endread_function($e);
                    }
                }
                break;
            case 'dc-function':
                if ($q->m_dcmode == 2) {
                    if (!$q->body_output->skip) {
                        // start dc function 
                        $q->body_output->skip($src, $e);
                        $q->m_dcmode = self::SKIP_READ_FUNC;
                        $info->filter = false;
                        $info->condition_start = $q->type;
                        $info->condition_start_condition = $q->getConditions();
                        php_code_handle_core($info, $e, $src, $reflector);
                    }
                }
                break;
            default:
                if ($q->m_dcmode == self::END) {
                    $q->_closeBuffer($e, $src, $info, $reflector);
                }
                break;
        }
        return true;
    }
    public function getConditions()
    {
        $p = [];
        $q = $this;
        while ($q) {
            if ($q->type == 'else') {
                // invert all 
            }
            $p[] = $q->cond;
            $q = $q->parent;
        }
        return sprintf(count($p) > 1 ? '(%s)' : '%s', implode(' && ', $p));
    }
    protected function _endread_function($e)
    {
        $q = $this;
        $q->body_output->start = $e->to;
        $q->body_output->skip = false;
        $q->m_dcmode = 2;
    }
}
