<?php
namespace Think\Blade\Engines;

use Think\Blade\Contracts\View\Engine;
use Think\Blade\Filesystem\Filesystem;

class FileEngine implements Engine
{
    /**
     * Get the evaluated contents of the view.
     *
     * @param  string  $path
     * @param  array  $data
     * @return string
     */
    public function get($path, array $data = [])
    {
        return file_get_contents($path);
    }
}
