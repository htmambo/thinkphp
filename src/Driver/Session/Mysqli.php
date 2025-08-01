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

namespace Think\Driver\Session;

/**
 * 数据库方式Session驱动
 *    CREATE TABLE think_session (
 *      session_id varchar(255) NOT NULL,
 *      session_expire int(11) NOT NULL,
 *      session_data blob,
 *      UNIQUE KEY `session_id` (`session_id`)
 *    );
 */
class Mysqli
{

    /**
     * Session有效时间
     */
    protected $lifeTime = '';

    /**
     * session保存的数据库名
     */
    protected $sessionTable = '';

    /**
     * 数据库句柄
     */
    protected $handler = array();

    /**
     * 打开Session
     * @access public
     * @param string $savePath
     * @param mixed $sessName
     */
    public function open($savePath, $sessName)
    {
        $this->lifeTime     = C('SESSION_EXPIRE') ? C('SESSION_EXPIRE') : ini_get('session.gc_maxlifetime');
        $this->sessionTable = C('SESSION_TABLE') ? C('SESSION_TABLE') : C("DB_PREFIX") . "session";
        //分布式数据库
        $host = explode(',', C('DB_HOST'));
        $port = explode(',', C('DB_PORT'));
        $name = explode(',', C('DB_NAME'));
        $user = explode(',', C('DB_USER'));
        $pwd  = explode(',', C('DB_PWD'));
        $r      = floor(mt_rand(0, count($host) - 1));
        $hosts = [$r];
        if (1 == C('DB_DEPLOY_TYPE')) {
            //读写分离
            if (C('DB_RW_SEPARATE')) {
                $w = floor(mt_rand(0, C('DB_MASTER_NUM') - 1));
                if (is_numeric(C('DB_SLAVE_NO'))) {
                    //指定服务器读
                    $r = C('DB_SLAVE_NO');
                } else {
                    $r = floor(mt_rand(C('DB_MASTER_NUM'), count($host) - 1));
                }
                //主数据库链接
                $hosts = [
                    $w, $r
                ];
            }
        }
        foreach($hosts as $i => $id)
        {
            $handler = mysqli_connect(
                $host[$id] . (isset($port[$id]) ? ':' . $port[$id] : ':' . $port[0]),
                isset($user[$id]) ? $user[$id] : $user[0],
                isset($pwd[$id]) ? $pwd[$id] : $pwd[0]
            );
            $dbSel = mysqli_select_db(
                $handler,
                isset($name[$id]) ? $name[$id] : $name[0]
            );
            if (!$handler || !$dbSel) {
                return false;
            }
            $this->handler[$i] = $handler;
        }
        return true;
    }

    /**
     * 关闭Session
     * @access public
     */
    public function close()
    {
        $this->gc($this->lifeTime);
        $result = true;
        foreach($this->handler as $handler) {
            $result = $result && mysqli_close($handler);
        }
        return $result;
    }

    /**
     * 读取Session
     * @access public
     * @param string $sessID
     */
    public function read($sessID)
    {
        $handler = count($this->handler)>1 ? $this->handler[1] : $this->handler[0];
        $res    = mysqli_query($handler, "SELECT session_data AS data FROM " . $this->sessionTable . " WHERE session_id = '$sessID'   AND session_expire >" . time());
        if ($res) {
            $row = mysqli_fetch_assoc($res);
            return $row['data'];
        }
        return "";
    }

    /**
     * 写入Session
     * @access public
     * @param string $sessID
     * @param String $sessData
     */
    public function write($sessID, $sessData)
    {
        $handler = $this->handler[0];
        $expire = time() + $this->lifeTime;
        mysqli_query($handler, "REPLACE INTO  " . $this->sessionTable . " (  session_id, session_expire, session_data)  VALUES( '$sessID', '$expire',  '$sessData')");
        if (mysqli_affected_rows($handler)) {
            return true;
        }

        return false;
    }

    /**
     * 删除Session
     * @access public
     * @param string $sessID
     */
    public function destroy($sessID)
    {
        $handler = $this->handler[0];
        mysqli_query($handler, "DELETE FROM " . $this->sessionTable . " WHERE session_id = '$sessID'");
        if (mysqli_affected_rows($handler)) {
            return true;
        }

        return false;
    }

    /**
     * Session 垃圾回收
     * @access public
     * @param string $sessMaxLifeTime
     */
    public function gc($sessMaxLifeTime)
    {
        $handler = $this->handler[0];
        mysqli_query($handler, "DELETE FROM " . $this->sessionTable . " WHERE session_expire < " . time());
        return mysqli_affected_rows($handler);
    }

}
