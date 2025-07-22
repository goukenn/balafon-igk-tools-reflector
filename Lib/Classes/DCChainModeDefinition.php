<?php
// @author: C.A.D. BONDJE DOUE
// @file: DCChainModeDefinition.php
// @date: 20250711 10:03:56
namespace igk\tools\Reflector;


/**
* 
* @package igk\tools\Reflector
* @author C.A.D. BONDJE DOUE
*/
class DCChainModeDefinition{
    /**
     * curl level of this chain definition the first '{' = will mark the level to 0
     * @var int
     */
    var $level = -1; 
    /**
     * type of this chain definition 
     * @var mixed
     */
    var $type;
    /**
     * parent of this chain mode definition 
     * @var mixed
     */
    var $parent;
    /**
     * chain mode to restore 
     * @var mixed
     */
    var $dcmode;

    var $function;

    var $class;
    
    var $trait;

    var $interface;

    /**
     * 
     * @param mixed $info 
     * @return void 
     */
    public function clean($info){
        switch($this->type){
            case 'function':
                $info->function = null;
                break;
        }
    }
}