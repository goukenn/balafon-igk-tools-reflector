<?php
// @author: C.A.D. BONDJE DOUE
// @file: FormatPhpCodeCommand.php
// @date: 20250710 06:55:10
namespace igk\tools\Reflector\System\Console\Commands;

use IGK\System\Console\AppExecCommand;
use IGK\System\Console\Logger as ConsoleLogger;
use Logger;

use function igk\tools\Reflector\treat_files;
use function igk\tools\Reflector\harmonize_render;

/**
* 
* @package igk\tools\Reflector\System\Console\Commands
* @author C.A.D. BONDJE DOUE
*/
class FormatPhpCodeCommand extends AppExecCommand{
	var $command="--reflector:format";
	var $desc="format php code.";
	var $category="reflector";
	var $options=[

	];
	var $usage='file [options]';
	public function exec($command, ?string $file=null) { 
		igk_is_null_or_empty($file) && igk_die('missing file'); 
 
		$s = treat_files($file); 
		if($s){

			ConsoleLogger::print(str_repeat('-', 40));
			echo harmonize_render($s);
		}  
	}
}