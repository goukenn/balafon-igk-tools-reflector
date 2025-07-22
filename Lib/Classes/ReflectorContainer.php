<?php
// @author: C.A.D. BONDJE DOUE
// @filename: ReflectorContainer.php
// @date: 20250703 14:44:13
// @desc: 
namespace igk\tools\Reflector;
/**
 * 
 * @package 
 */
class ReflectorContainer{
    /**
     * 
     * @var mixed
     */
    var $classes;
    /**
     * total interface 
     * @var mixed
     */
    var $interfaces;
    /**
     * total traits loaded
     * @var mixed
     */
    var $traits;
    /**
     * files association loaded
     * @var mixed
     */
    var $files;
    var $global_uses;
    var $functions;
    /**
     * determine if mixed with html text presentation
     * @var bool
     */
    var $html = false;
    /**
     * global comment
     * @var mixed
     */
    var $global_comments;
    /**
     * 
     * @var bool
     */
    var $singleDefinitionPerFile = true;
    var $noFunctionBody = false;
    var $renderMultiple= false;
    var $namespace;
    /**
     * globa definition script 
     * @var mixed
     */
    var $global_script;
}