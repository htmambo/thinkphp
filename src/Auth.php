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
namespace Think;

/**
 * 权限认证类
 * 功能特性：
 * 1，是对规则进行认证，不是对节点进行认证。用户可以把节点当作规则名称实现对节点进行认证。
 *      $auth=new Auth();  $auth->check('规则名称','用户id')
 * 2，可以同时对多条规则进行认证，并设置多条规则的关系（or或者and）
 *      $auth=new Auth();  $auth->check('规则1,规则2','用户id','and')
 *      第三个参数为and时表示，用户需要同时具有规则1和规则2的权限。 当第三个参数为or时，表示用户值需要具备其中一个条件即可。默认为or
 * 3，一个用户可以属于多个用户组(think_auth_group_access表 定义了用户所属用户组)。我们需要设置每个用户组拥有哪些规则(think_auth_group 定义了用户组权限)
 *
 * 4，支持规则表达式。
 *      在think_auth_rule 表中定义一条规则时，如果type为1， condition字段就可以定义规则表达式。
 *      如定义{score}>5  and {score}<100表示用户的分数在5-100之间时这条规则才会通过。
 */
//数据库
/*

-- ----------------------------
-- think_auth_extend，用户组扩展权限
-- id:主键，name：规则唯一标识, title：规则中文名称 status 状态：为1正常，为0禁用，condition：规则表达式，为空表示存在就验证，不为空表示按照条件验证
-- ----------------------------
CREATE TABLE `think_auth_extend` (
   `group_id` mediumint(10) UNSIGNED NOT NULL COMMENT '用户id',
   `extend_id` mediumint(8) UNSIGNED NOT NULL COMMENT '扩展表中数据的id',
   `type` tinyint(1) UNSIGNED NOT NULL COMMENT '扩展类型标识 1:栏目分类权限;2:...',
   `auth` tinyint(1) UNSIGNED NOT NULL COMMENT '扩展权限类型 1:完整权限;2:只读',
   UNIQUE KEY `group_extend_type` (`group_id`,`extend_id`,`type`) USING BTREE,
   KEY `group_id` (`group_id`) USING BTREE,
   KEY `extend_id` (`extend_id`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='用户组与分类的对应关系表';
-- ----------------------------
-- think_auth_group 用户组表，
-- id：主键， title:用户组中文名称， rules：用户组拥有的规则id， 多个规则","隔开，status 状态：为1正常，为0禁用
-- ----------------------------
DROP TABLE IF EXISTS `think_auth_group`;
CREATE TABLE `think_auth_group` (
   `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
   `type` tinyint(4) NOT NULL DEFAULT 0 COMMENT '组类型',
   `title` char(20) NOT NULL DEFAULT '' COMMENT '用户组中文名称',
   `description` varchar(80) NOT NULL DEFAULT '' COMMENT '描述信息',
   `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '用户组状态：为1正常，为0禁用,-1为删除',
   `rules` varchar(500) NOT NULL DEFAULT '' COMMENT '用户组拥有的规则id，多个规则 , 隔开'
   PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
-- ----------------------------
-- think_auth_group_access 用户组明细表
-- uid:用户id，group_id：用户组id
-- ----------------------------
DROP TABLE IF EXISTS `think_auth_group_access`;
CREATE TABLE `think_auth_group_access` (
   `uid` mediumint(8) unsigned NOT NULL COMMENT '用户id',
   `group_id` mediumint(8) unsigned NOT NULL COMMENT '用户组id',
   UNIQUE KEY `uid_group_id` (`uid`,`group_id`),
   KEY `uid` (`uid`),
   KEY `group_id` (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
-- ----------------------------
-- think_auth_rule，规则表，
-- id:主键，name：规则唯一标识, title：规则中文名称 status 状态：为1正常，为0禁用，condition：规则表达式，为空表示存在就验证，不为空表示按照条件验证
-- ----------------------------
DROP TABLE IF EXISTS `think_auth_rule`;
CREATE TABLE `think_auth_rule` (
   `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT COMMENT '规则id,自增主键',
   `module` varchar(20) NOT NULL COMMENT '规则所属module',
   `name` char(80) NOT NULL DEFAULT '' COMMENT '规则唯一英文标识',
   `title` char(20) NOT NULL DEFAULT '' COMMENT '规则中文描述',
   `type` tinyint(2) NOT NULL DEFAULT 1 COMMENT '1-url;2-主菜单;3-自定义标识',
   `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否有效(0:无效,1:有效)',
   `condition` varchar(500) NOT NULL DEFAULT '' COMMENT '规则附件条件,满足附加条件的规则,才认为是有效的规则'
   PRIMARY KEY (`id`),
   UNIQUE KEY `name` (`name`),
   KEY `module` (`module`,`status`,`type`) USING BTREE
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
*/
class Auth
{
    //默认配置
    protected $_config = array(
        'AUTH_ON' => true,
        // 认证开关
        'AUTH_TYPE' => 1,
        // 认证方式，1为实时认证；2为登录认证。
        'AUTH_GROUP' => 'auth_group',
        // 用户组数据表名
        'AUTH_GROUP_ACCESS' => 'auth_group_access',
        // 用户-用户组关系表
        'AUTH_RULE' => 'auth_rule',
        // 权限规则表
        'AUTH_EXTEND' => 'auth_extend',
        // 权限扩展
        'AUTH_USER' => 'member',
        // 用户信息表
        'LOGIN_UID' => '@session.login.uid',
    );
    // 管理员用户组类型标识
    const ADMIN_GROUP_TYPE = 1;
    // 分类权限标识
    const AUTH_EXTEND_CATEGORY_TYPE = 1;
    // 模型权限标识
    const AUTH_EXTEND_MODEL_TYPE = 2;
    // 完整权限
    const AUTH_EXTEND_FULL = 1;
    // 只读权限
    const AUTH_EXTEND_READONLY = 2;
    private $error = '';

    private Model $aeModel;

    private Model $agModel;

    private Model $arModel;

    private Model $agaModel;

    private Model $auModel;
    public function __construct()
    {
        $prefix = C('DB_PREFIX');
        $this->_config['AUTH_EXTEND'] = $prefix . $this->_config['AUTH_EXTEND'];
        $this->_config['AUTH_GROUP'] = $prefix . $this->_config['AUTH_GROUP'];
        $this->_config['AUTH_RULE'] = $prefix . $this->_config['AUTH_RULE'];
        $this->_config['AUTH_USER'] = $prefix . $this->_config['AUTH_USER'];
        $this->_config['AUTH_GROUP_ACCESS'] = $prefix . $this->_config['AUTH_GROUP_ACCESS'];
        if ($_config = C('AUTH_CONFIG')) {
            //可设置配置项 AUTH_CONFIG, 此配置项为数组。
            if (is_string($_config)) {
                $_config = array();
            }
            foreach ($_config as $k => $v) {
                $v = trim($v);
                if (isset($this->_config[$k]) && $v) {
                    $this->_config[$k] = $v;
                }
            }
        }
        $this->aeModel = M($this->_config['AUTH_EXTEND'], NULL);
        $this->agModel = M($this->_config['AUTH_GROUP'], NULL);
        $this->arModel = M($this->_config['AUTH_RULE'], NULL);
        $this->agaModel = M($this->_config['AUTH_GROUP_ACCESS'], NULL);
        $this->auModel = M($this->_config['AUTH_USER'], NULL);
    }
    /**
     * 检查权限
     *
     * @param string $name     需要验证的规则列表,支持逗号分隔的权限规则或索引数组
     * @param int    $uid      认证用户的id
     * @param int    $type
     * @param string $mode     执行check的模式
     * @param string $relation 如果为 'or' 表示满足任一条规则即通过验证;如果为 'and'则表示需满足所有规则才能通过验证
     * @return bool 通过验证返回true;失败返回false
     */
    public function check($name, $uid, $type = 1, $mode = 'url', $relation = 'or')
    {
        if (!$this->_config['AUTH_ON']) {
            return true;
        }
        $allAuthList = $this->getAuthList(0, $type);
        $authList = $this->getAuthList($uid, $type);
        //获取用户需要验证的所有有效规则列表
        if (is_string($name)) {
            $name = strtolower($name);
            if (strpos($name, ',') !== false) {
                $name = explode(',', $name);
            } else {
                $name = array($name);
            }
        }
        if (is_string($name)) {
            $name = explode(',', $name);
        }
        // $name 中是当前需要验证的权限列表，需要清理掉不需要验证的权限规则
        $needCheckAuths = array_intersect_assoc($name, $allAuthList);
        // 如果需要验证的权限列表为空，则返回true
        if (empty($needCheckAuths)) {
            return true;
        }
        $list = array();
        //保存验证通过的规则名
        if ('url' == $mode) {
            $REQUEST = json_decode(strtolower(json_encode($_REQUEST)), true);
        }
        foreach ($authList as $auth) {
            $query = preg_replace('/^.+\?/U', '', $auth);
            if ('url' == $mode && $query != $auth) {
                parse_str($query, $param);
                //解析规则中的param
                $intersect = array_intersect_assoc($REQUEST, $param);
                $auth = preg_replace('/\?.*$/U', '', $auth);
                if (in_array($auth, $name) && $intersect == $param) {
                    //如果节点相符且url参数满足
                    $list[] = $auth;
                }
            } else if (in_array($auth, $name)) {
                $list[] = $auth;
            }
        }
        if ('or' == $relation and !empty($list)) {
            return true;
        }
        $diff = array_diff($name, $list);
        if ('and' == $relation and empty($diff)) {
            return true;
        }
        return false;
    }
    /**
     * 返回用户所属用户组信息
     *
     * @param int $uid                         用户id
     * @return array  用户所属的用户组 array(
     *                                         array('uid'=>'用户id','group_id'=>'用户组id','title'=>'用户组名称','rules'=>'用户组拥有的规则id,多个,号隔开'),
     *                                         ...)
     */
    public function getUserGroup($uid)
    {
        static $groups = array();
        if (isset($groups[$uid])) {
            return $groups[$uid];
        }
        $user_groups = $this->agaModel->alias('a')->field('uid,group_id,title,description,rules,type')->join($this->_config['AUTH_GROUP'] . " g on a.group_id=g.id", 'LEFT')->where("a.uid='{$uid}' and g.status='1'")->select();
        $groups[$uid] = $user_groups ? $user_groups : array();
        return $groups[$uid];
    }
    /**
     * 获得权限列表
     *
     * @param integer $uid 用户id
     * @param integer $type
     */
    protected function getAuthList($uid, $type)
    {
        static $_authList = array();
        //保存用户验证通过的权限列表
        $t = implode(',', (array) $type);
        if (isset($_authList[$uid . '@' . $t])) {
            return $_authList[$uid . '@' . $t];
        }
        $map = array('type' => $type, 'status' => 1);
        if ($uid) {
            //读取用户所属用户组
            $groups = $this->getUserGroup($uid);
            $ids = array();
            //保存用户所属用户组设置的所有权限规则id
            foreach ($groups as $g) {
                $ids = array_merge($ids, explode(',', trim($g['rules'], ',')));
            }
            $ids = array_unique($ids);
            if (empty($ids)) {
                $_authList[$uid . '@' . $t] = array();
                return array();
            }
            $map = array('id' => array('in', $ids), 'type' => $type, 'status' => 1);
        }
        // 读取用户组所有权限规则
        $model = new Model();
        $rules = $model->table($this->_config['AUTH_RULE'])->where($map)->field('condition,name')->select();
        // 循环规则，判断结果。
        $authList = array();
        foreach ($rules as $rule) {
            if (!empty($rule['condition'])) {
                // 根据condition进行验证
                $user = $this->getUserInfo($uid);
                //获取用户信息,一维数组
                // 安全修复: 移除危险的eval()调用
                // 简化条件检查，仅支持基本的相等比较
                $condition = false;
                if (preg_match('/\{(\w+)\}\s*==\s*["\']?(\w+)["\']?/', $rule['condition'], $matches)) {
                    $field = $matches[1];
                    $value = $matches[2];
                    $condition = isset($user[$field]) && $user[$field] == $value;
                }
                if ($condition) {
                    $authList[] = strtolower($rule['name']);
                }
            } else {
                //只要存在就记录
                $authList[] = strtolower($rule['name']);
            }
        }
        $_authList[$uid . '@' . $t] = $authList;
        return array_unique($authList);
    }
    /**
     * 获得用户资料,根据自己的情况读取数据库
     */
    protected function getUserInfo($uid)
    {
        static $userinfo = array();
        if (!isset($userinfo[$uid])) {
            $userinfo[$uid] = $this->auModel->find($uid);
        }
        return $userinfo[$uid];
    }
    /**
     * 把用户添加到用户组,支持批量添加用户到用户组
     *
     * 示例: 把uid=1的用户添加到group_id为1,2的组 `AuthGroupModel->addToGroup(1,'1,2');`
     *
     * @param int $uid 用户id
     * @param int $gid 组id
     * @return bool
     */
    public function addToGroup($uid, $gid)
    {
        $uid = is_array($uid) ? $uid : explode(',', trim($uid, ','));
        $gid = is_array($gid) ? $gid : explode(',', trim($gid, ','));
        $uid_arr = array_diff($uid, array(C('USER_ADMINISTRATOR')));
        $add = array();
        foreach ($uid_arr as $u) {
            foreach ($gid as $g) {
                if (is_numeric($u) && is_numeric($g)) {
                    $add[] = array('group_id' => $g, 'uid' => $u);
                }
            }
        }
        if ($add) {
            $this->agaModel->addAll($add, [], true);
        }
        if ($this->agaModel->getDbError()) {
            if (count($uid_arr) == 1 && count($gid) == 1) {
                //单个添加时定制错误提示
                $this->error = L('Cannot be added repeatedly');
            }
            return false;
        } else {
            return true;
        }
    }
    public static function checkAuth()
    {
        $ary = func_get_args();
        $priv = array_shift($ary);
        $priv = preg_replace('@\s@i', '', $priv);
        if (substr($priv, 0, 1) == '!') {
            return false;
        }
        return true;
    }
    /**
     * 返回用户拥有管理权限的扩展数据id列表
     *
     * @param int $uid  用户id
     * @param int $type 扩展数据标识
     * @return array
     *
     *  array(2,4,8,13)
     *
     */
    public function getExtendOfUser($uid, $type)
    {
        static $_authList = array();
        //保存用户验证通过的权限列表
        $uid = max(0, intval($uid));
        $type = max(0, intval($type));
        if (!$uid) {
            $this->error = L('Please specify the UID to check');
            return false;
        }
        if (!$type) {
            $this->error = L('Please specify the extension to check');
            return false;
        }
        if (isset($_authList[$uid][$type])) {
            return $_authList[$uid][$type];
        }
        $map = ['g.uid' => $uid, 'c.type' => $type];
        $map[] = '!isnull(extend_id)';
        $rows = $this->agaModel->alias('g')->join($this->_config['AUTH_EXTEND'] . ' c on g.group_id=c.group_id', 'LEFT')->where($map)->field('c.extend_id,c.auth')->select();
        if ($rows === false) {
            $this->error = $this->agaModel->getError();
            return false;
        }
        $result = $this->_processExtendData($rows);
        $_authList[$uid][$type] = $result;
        return $result;
    }
    /**
     * 获取用户组授权的扩展信息数据
     *
     * @param int $gid 用户组id
     * @return array
     *
     *  array(2,4,8,13)
     *
     */
    public function getExtendOfGroup($gid, $type)
    {
        static $_authList = array();
        //保存用户验证通过的权限列表
        $gid = max(0, intval($gid));
        $type = max(0, intval($type));
        if (!$gid) {
            $this->error = L('Please specify the group ID to check');
            return false;
        }
        if (!$type) {
            $this->error = L('Please specify the extension to check');
            return false;
        }
        if (isset($_authList[$gid][$type])) {
            return $_authList[$gid][$type];
        }
        $map = ['group_id' => $gid, 'type' => $type];
        $rows = $this->aeModel->where($map)->field('extend_id,auth')->select();
        if ($rows === false) {
            $this->error = $this->agaModel->getError();
            return false;
        }
        $result = $this->_processExtendData($rows);
        $_authList[$gid][$type] = $result;
        return $result;
    }
    private function _processExtendData($rows)
    {
        $result = [];
        foreach ($rows as $row) {
            $eid = $row['extend_id'];
            $auth = $row['auth'];
            if (isset($result[$eid])) {
                $result[$eid] &= $auth;
            } else {
                $result[$eid] = $auth;
            }
        }
        return $result;
    }
    /**
     * 批量设置用户组可管理的扩展权限数据
     *
     * @param int|string|array $gid 用户组id
     * @param int|string|array $cid 分类id
     *
     * @retrun bool
     */
    public function addExtendToGroup($gid, $cid, $type)
    {
        $gid = is_array($gid) ? $gid : explode(',', trim($gid, ','));
        $cid = is_array($cid) ? $cid : explode(',', trim($cid, ','));
        $add = array();
        foreach ($gid as $g) {
            foreach ($cid as $c) {
                if (is_numeric($g) && is_numeric($c)) {
                    $add[] = array('group_id' => $g, 'extend_id' => $c, 'type' => $type);
                }
            }
        }
        $result = true;
        if ($add) {
            $result = $this->aeModel->addAll($add, [], true);
        }
        if ($result === false) {
            $this->error = $this->aeModel->getError();
        } else {
            $result = true;
        }
        return $result;
    }
    /**
     * 将用户从用户组中移除
     *
     * @param int|string|array $uid 用户id
     * @param int|string|array $gid 用户组id
     *
     * @retrun bool
     */
    public function removeUidFromGroup($uid, $gid)
    {
        $map = [];
        if (is_array($uid)) {
            $map['uid'] = ['IN', $uid];
        } else {
            $map['uid'] = intval($uid);
        }
        if (is_array($gid)) {
            $map['group_id'] = ['IN', $gid];
        } else {
            $map['group_id'] = intval($gid);
        }
        return $this->agaModel->where($map)->delete();
    }
    /**
     * 获取某个用户组的用户ID列表
     *
     * @param int $group_id 用户组id
     *
     * @return array|bool
     */
    public function getUidsInGroup($group_id)
    {
        $map = ['group_id' => $group_id];
        return $this->agaModel->where($map)->getField('uid', true);
    }
}