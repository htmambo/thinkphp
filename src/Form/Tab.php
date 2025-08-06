<?php
namespace Think\Form;

class Tab
{
    /**
     * @var string
     */
    private $name;
    private $form;
    private $elements = [];

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->form = new Form();
    }
    
    /**
     * 添加表单字段
     * @param mixed $field
     * @return $this
     */
    public function addField($field, $label = '', $type = 'text', $options = [])
    {
        $element = call_user_func([$this->form, $type], $field, $label, $options);
        $this->elements[] = &$element;
        return $this;
    }
    
    /**
     * 获取tab名称
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * 渲染tab内容
     * @return array
     */
    public function render($options): array
    {
        $data = [
            'content' => [],
            'laymodule' => [],
            'js' => [],
            'script' => [],
            'css' => [],
            'style' => []
        ];
        $keys    = ['laymodule', 'js', 'script', 'css', 'style'];
        foreach ($this->elements as $element) {
            $result = $element->render(null, $options);
            $data['content'][] = $result['content'];
            foreach ($keys as $k) {
                if (isset($result[$k]) && $result[$k]) {
                    $data[$k] = array_merge($data[$k], $result[$k]);
                }
                $data[$k] = array_unique($data[$k]);
            }
        }
        $data['content'] = implode('', $data['content']);
        return $data;
    }

}

