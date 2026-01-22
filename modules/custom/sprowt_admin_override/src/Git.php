<?php

namespace Drupal\sprowt_admin_override;

use Symfony\Component\Process\Process;

class Git
{
    public static function gitCommand($dir, array $cmd = [], $asArray = false) {
        $cmdArray = array_merge(['git'], $cmd);
        $process = new Process($cmdArray);
        $process->setWorkingDirectory($dir);
        $process->run();
        $output = $process->getOutput();
        $err = $process->getErrorOutput();
        if($asArray) {
            $return = str_replace(["\n","\r\n"], "@|--|@", $output);
            $return = explode('@|--|@', $return);
        }
        else {
            $return = str_replace(["\n", "\r\n"], '', $output);
        }
        $process->stop();
        gc_collect_cycles();
        return $return;
    }

    public static function getHash($dir)
    {
        return static::gitCommand($dir, ['rev-parse', 'HEAD']);
    }

    public static function getMiniHash($dir)
    {
        return static::gitCommand($dir, ['describe', '--always']);
    }

    public static function getCurrentBranch($dir) {
        $head = static::gitCommand($dir, ['symbolic-ref', '-q', '--short', 'HEAD']);
        if(!empty($head)) {
            return $head;
        }
        return static::gitCommand($dir, ['describe', '--tags', '--exact-match']);
    }

    public static function getVersion($dir)
    {
        $refs = static::gitCommand($dir, ['show-ref'], true);
        $tags = [];

        foreach($refs as $ref) {
            if ($pos = strpos($ref, 'refs/tags')) {
                $tags[] = preg_replace('/.*refs\/tags\/(.*?)$/', '$1', $ref);
            }
        }

        $currentHead = static::getCurrentBranch($dir);

        if (in_array($currentHead, $tags)) {
            return $currentHead;
        }

        return $currentHead . '--' . static::getMiniHash($dir);
    }
}
