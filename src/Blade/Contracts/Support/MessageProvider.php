<?php
namespace Think\Blade\Contracts\Support;

interface MessageProvider
{
    /**
     * Get the messages for the instance.
     *
     * @return \Think\Blade\Contracts\Support\MessageBag
     */
    public function getMessageBag();
}
