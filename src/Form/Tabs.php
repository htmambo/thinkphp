<?php
namespace Think\Form;

class Tabs
{
    /**
     * @var array
     */
    public $tabs = [];

    /**
     * @var Tab|null
     */
    private $currentTab = null;

    /**
     * @var bool 是否正在进行tab操作
     */
    private $inTabOperation = false;

    /**
     * 创建并添加新的tab，或切换到已存在的tab
     * @param string $name
     * @return $this
     */
    public function tab(string $name, $options = []): self
    {
        $this->inTabOperation = true;

        // 检查tab是否已存在
        foreach ($this->tabs as $tab) {
            if ($tab->getName() === $name) {
                $this->currentTab = $tab;
                return $this;
            }
        }

        // 创建新tab
        $tab = new Tab($name, $options);
        $this->tabs[] = $tab;
        $this->currentTab = $tab;
        return $this;
    }

    /**
     * 检查是否正在进行tab操作
     * @return bool
     */
    public function isInTabOperation(): bool
    {
        return $this->inTabOperation;
    }

    /**
     * 向当前tab添加字段
     * @param string $type
     * @param string $name
     * @param string $label
     * @param array $options
     * @return $this
     * @throws \Exception
     */
    public function addField($field, $label = '', $type = 'text', $options = [])
    {
        if (!$this->currentTab) {
            throw new \Exception('No tab selected. Call tab() first.');
        }

        $this->currentTab->addField($field, $label, $type, $options);
        return $this;
    }

    /**
     * 结束tab操作
     * @return Form
     */
    public function endTabs()
    {
        $this->inTabOperation = false;
        $this->currentTab = null;
        return $this->parentForm;
    }

    /**
     * 添加已存在的tab对象
     * @param Tab $tab
     * @return $this
     */
    public function addTab(Tab $tab)
    {
        $this->tabs[] = $tab;
        $this->currentTab = $tab;
        return $this;
    }

    /**
     * 获取当前tab
     * @return Tab|null
     */
    public function getCurrentTab(): ?Tab
    {
        return $this->currentTab;
    }

    /**
     * 渲染整个tabs内容
     * @return array
     */
    public function render($options): array
    {
        $html = '<div class="layui-tab layui-tab-brief" lay-filter="form-tab">';
        $html .= '<ul class="layui-tab-title">';

        foreach ($this->tabs as $index => $tab) {
            $activeClass = $index === 0 ? ' layui-this' : '';
            $html .= sprintf('<li class="%s">%s</li>', $activeClass, htmlspecialchars($tab->getName()));
        }

        $html .= '</ul>';
        $html .= '<div class="layui-tab-content">';

        // 需要将额外的信息如css,style,js等合并返回
        $result = [];
        foreach ($this->tabs as $index => $tab) {
            $activeClass = $index === 0 ? ' layui-show' : '';
            $html .= sprintf('<div class="layui-tab-item%s">', $activeClass);
            $ret = $tab->render($options);
            $html .= $ret['content'];
            unset($ret['content']);
            foreach($ret as $k => $v) {
                if(!$v) continue;
                if(!isset($result[$k])) {
                    $result[$k] = [];
                }
                if(is_array($v)) {
                    $result[$k] = array_merge($result[$k], $v);
                } else {
                    pre($k . ' === ' . $v);
                    $result[$k][] = $v;
                }
            }
            $html .= '</div>';
        }

        $html .= '</div></div>';
        $result['content'] = $html;
        return $result;
    }
}