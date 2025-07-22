<?php
// @author: C.A.D. BONDJE DOUE
// @file: ReflectorCodeFilterFactory.php
// @date: 20250721 16:33:08
namespace igk\tools\Reflector;

use IGK\System\Text\RegexMatcherCapture;

/**
* 
* @package igk\tools\Reflector
* @author C.A.D. BONDJE DOUE
*/
abstract class ReflectorCodeFilterFactory{
    /**
     * 
     * @var ?static
     */
    var $parent;

    var $type;

    var $start;

    var $to;

    /**
     * create a conditional filter - that is a conditional filter 
     * @param mixed $name 
     * @return static|void 
     */
    public static function CreateFilter($name){
        if (in_array($name, ['if','else','elseif'])){
            $r = new ReflectorCodeConditionalFilter();
            $r->type = $name;
            return $r;
        }
    }
    abstract function render():string;
    /**
     * filter reading
     * @param mixed $e 
     * @param string $src 
     * @param mixed $info 
     * @param mixed $reflector 
     * @return bool 
     */
    abstract function filter(RegexMatcherCapture $e, string $src, $info, ReflectorContainer $reflector): bool;
}