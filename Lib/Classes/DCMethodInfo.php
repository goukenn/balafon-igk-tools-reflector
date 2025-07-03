<?php
// @author: C.A.D. BONDJE DOUE
// @filename: DCMethodInfo.php
// @date: 20250703 14:44:30
// @desc: 

namespace igk\tools\Reflector;
/**
 * method info 
 * @package 
 */
class DCMethodInfo{
    /**
     * 
     * @var ?string
     */
    var $name;
    var $modifiers;
    var $condition;
    var $code;
    var $returnType;
    var $isRef;

    /**
     * doc comment
     * @var ?string
     */
    var $phpDocComment;
    public function isEmpty(){
        return empty($this->code);
    }
}