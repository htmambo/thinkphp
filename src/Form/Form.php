<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace Think\Form;

use Think\Form\Traits\Form as BaseForm;

class Form
{
    /**
     * Available fields.
     *
     * @var array
     */
    protected static $availableFields = [
        'switcher'       => Field\Switcher::class,
        'text'           => Field\Text::class,
        'checkbox'       => Field\Checkbox::class,
        'checkboxes'     => Field\Checkboxes::class,
        'select'         => Field\Select::class,
        'radio'          => Field\Radio::class,
        'radios'         => Field\Radios::class,
        'button'         => Field\Button::class,
        'buttons'        => Field\Buttons::class,
        'hidden'         => Field\Hidden::class,
        'textarea'       => Field\Textarea::class,
        'year'           => Field\Year::class,
        'month'          => Field\Month::class,
        'date'           => Field\Date::class,
        'datetime'       => Field\Datetime::class,
        'rate'           => Field\Rate::class,
        'time'           => Field\Time::class,
        'slider'         => Field\Slider::class,
        'password'       => Field\Password::class,
        'selectResource' => Field\SelectResource::class,
        'datetimeRange'  => Field\DatetimeRange::class,
        'dateRange'      => Field\DateRange::class,
        'timeRange'      => Field\TimeRange::class,
        'editor'         => Field\Editor::class,
        'tags'           => Field\Tags::class,
        'file'           => Field\File::class,

        'files'          => Field\Files::class,
        'image'          => Field\Image::class,
        'images'         => Field\Images::class,

        'icon'           => Field\Icon::class,
        'captcha'        => Field\Captcha::class,
        'tree'           => Field\Tree::class,
        'markdown'       => Field\Markdown::class,
    ];
    use BaseForm;
}
