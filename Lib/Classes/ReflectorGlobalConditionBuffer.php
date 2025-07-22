<?php
// @author: C.A.D. BONDJE DOUE
// @file: ReflectorGlobalConditionBuffer.php
// @date: 20250721 13:22:46
namespace igk\tools\Reflector;

use IGK\System\IO\StringBuilder;

/**
* 
* @package igk\tools\Reflector
* @author C.A.D. BONDJE DOUE
*/
class ReflectorGlobalConditionBuffer{
    private $m_buffer;
    /**
     * skip flag
     * @var mixed
     */
    var $skip;
    var $from;
    var $to;
    /**
     * current offset 
     * @var int
     */
    var $offset = 0;
    /**
     * where curl start 
     * @var mixed
     */
    var $start; 

    public function __construct()
    {
        $this->m_buffer = new StringBuilder;
    }
    public function isEmpty(){
        return $this->m_buffer->isEmpty();
    }
    public function __toString()
    {
        return $this->m_buffer.'';
    }
    public function read(string $src, $e){
        $start = $this->start;
        $offset = $this->offset ?? 0;
        $l = $start+1+$offset;
        $c = substr($src, $l , $e->from -$l).$e->value;
        $offset = $e->to - $l;
        $this->offset = $offset;
        return $c; 
    }
    public function replace(string $src, $e, $value){
        $start = $this->start;
        $offset = $this->offset ?? 0;
        $l = $start+1+$offset;
        $c = substr($src, $l , $e->from -$l).$value;
        $offset = $e->to - $l;
        $this->offset = $offset;
        return $c; 
    }
    /**
     * skip and start buffer 
     * @param mixed $src 
     * @param mixed $e 
     * @return void 
     */
    public function skip($src, $e){
        $this->offset = 0;
        $l = $this->replace($src, $e, '');
        $this->m_buffer->append($l);
        $this->skip = true;
        $this->start = $this->offset = 0;
    }
    /**
     * done reading 
     * @param ?string $src 
     * @param mixed $e 
     * @return string output string
     */
    public function done($src, $e):string{
        $l = substr($src, $this->start, ($e->to -1)-$this->start);
        $this->m_buffer->append($l);
        $this->skip = true;
        $this->start = $this->offset = 0;
        return $this->m_buffer.'';
    }
    /**
     * append value to buffer
     * @param string $value 
     * @param int $nextStart 
     * @return void 
     */
    public function append(string $value, int $nextStart=0){
        $this->m_buffer->append($value);
        $this->start = $nextStart;
        $this->offset = 0;
    }
}