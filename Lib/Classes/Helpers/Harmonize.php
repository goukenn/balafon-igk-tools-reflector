<?php
// @author: C.A.D. BONDJE DOUE
// @file: Harmonize.php
// @date: 20250704 22:11:38
namespace igk\tools\Reflector\Helpers;
use IGK\System\Console\Logger;

use function igk\tools\Reflector\get_formatter;
use function igk\tools\Reflector\is_multiple_def_entities;
use function igk\tools\Reflector\render_function;
/**
 * 
 * @package igk\tools\Reflector\Helpers
 * @author C.A.D. BONDJE DOUE
 */
class Harmonize
{
    public static function Render($v, $s)
    {
        $content = '';
        if (is_multiple_def_entities($v)) {
            // Logger::info('is mutiple: ' . $k);
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
            if ($c = igk_getv($v, 'function')) {
                if ($s->namespace) {
                    echo 'namespace ' . $s->namespace . ";\n";
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
            if (!$s->global_script->isEmpty()){
                $formatter = get_formatter();
                echo $formatter->format($s->global_script->output);
            }
            $content = ob_get_contents();
            if (!$found) {
                $content = '<?php' . "\n" . $content;
            }
            ob_end_clean();
        }
        return $content;
    }
}