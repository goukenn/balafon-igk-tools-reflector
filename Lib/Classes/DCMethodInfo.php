<?php
// @author: C.A.D. BONDJE DOUE
// @filename: DCMethodInfo.php
// @date: 20250703 14:44:30
// @desc: 
namespace igk\tools\Reflector;
/**
 * method/funciton definition storage 
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
     * the method definition is nested 
     * @var bool
     */
    var $isNested = false;
    /**
     * doc comment
     * @var ?string
     */
    var $phpDocComment;
    /**
     * 
     * @var bool
     */
    var $conditional = false;
    var $conditional_condition = null;
    public function isEmpty(){
        return empty($this->code);
    }
}