<?php
// @author: C.A.D. BONDJE DOUE
// @file: ArmonisePhpCodeCommand.php
// @date: 20250703 15:12:33
namespace igk\tools\Reflector\System\Console\Commands;
use Exception;
use IGK\System\Console\AppExecCommand;
use IGK\System\Console\Logger;
use IGKException;
use function igk\tools\Reflector\treat_files;
use function igk\tools\Reflector\is_multiple_def_entities;
use function igk\tools\Reflector\render_function;
/**
 * command run it to armonise the code 
 * @package igk\tools\Reflector\System\Console\Commands
 * @author C.A.D. BONDJE DOUE
 */
class ArmonisePhpCodeCommand extends AppExecCommand
{
	var $command = "--harmonize";
	var $desc = "armonise php code ";
	var $category = "tools";
	var $options = [
		'--regex' => 'pattern regex to pass in case of directory',
		'--update' => 'flag: update the source file',
		'--no-code' => 'flag: disable code loading',
		'--no-render' => 'flag: disable render',
	];
	var $usage = 'file_or_dir [options]';
	/**
	 * 
	 * @param mixed $command 
	 * @param null|string $dir 
	 * @return void 
	 * @throws Exception 
	 * @throws IGKException 
	 */
	public function exec($command, ?string $dir = null)
	{
		$dir ?? igk_die('missing params');
		file_exists($dir) || igk_die('is not an directory/file');
		$v_update = property_exists($command->options, '--update');
		$v_no_render = property_exists($command->options, '--no-render');
		$s = treat_files($dir, [
			'noCode' => property_exists($command->options, '--no-code'),
			'regex' => igk_getv($command->options, '--regex'),
			'recurive' => false
		]);
		$s->singleDefinitionPerFile = (count($s->classes) + count($s->traits) + count($s->interfaces)) == 1;
		$tc = [];
		foreach ($s->files as $k => $v) {
			if (isset($v['mixed'])) {
				continue;
			}
			$content = '';
			if (is_multiple_def_entities($v)) {
				Logger::info('is mutiple: ' . $k);
				$s->singleDefinitionPerFile = false;
				ob_start();
				echo "<?php\n";
				if ($s->global_comments) {
					echo implode("\n", $s->global_comments) . "\n";
				}
				$bck = $s->global_comments;
				$s->global_comments = [];
				$s->renderMultiple = false;
				// only on element can 'to namepsace definition 
				foreach (['function', 'interface', 'trait', 'class'] as $gk) {
					if ($c = igk_getv($v, $gk)) {
						ksort($c); 
						if ($gk == 'function') {
							render_function($c, $s); 
						} else { 
							echo implode("\n", array_map(function ($i) use ($s) {
								$k = $i->render();
								$s->renderMultiple = true;
								return $k;
							}, $c)) . "\n";
						}
					}
				}
				$s->renderMultiple = false;
				$s->global_comments = $bck;
				$content = ob_get_contents();
				ob_end_clean();
			} else {
				$s->singleDefinitionPerFile = true;
				ob_start();
				if ($s->global_comments) {
					echo implode("\n", $s->global_comments) . "\n";
					$s->renderMultiple = true;
				}
				if ($c = igk_getv($v, 'function')){
					if ($s->namespace){
						echo 'namespace '.$s->namespace.";\n";
						$s->renderMultiple = true;
					}
					ksort($c);
					render_function($c, $s); 
				}
				$found = false;
				foreach (['interface', 'trait', 'class'] as $gk) {
					if ($c = igk_getv($v, $gk)) {
						echo $c[key($c)]->render();
						$found = true;
						break;
					}
				}
				$content = ob_get_contents();
				if(!$found){
					$content = '<?php'."\n".$content;
				}
				ob_end_clean();
			}
			if ($content) {
				if ($v_update) {
					Logger::info('update: ' . $k);
					igk_io_w2file($k, $content);
				};
				if (!$v_no_render) {
					echo $content;
				}
			}
		}
		
		Logger::success('done');
	}
}